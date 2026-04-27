<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AiCancellationService;
use App\Service\CarbonFootprintService;
use App\Service\LoyaltyPointsService;
use App\Service\OfferService;
use App\Service\FlouciPaymentService;
use App\Service\ReservationService;
use App\Message\SendSmsMessage;
use App\Service\ValidationService;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Service\MailerService;
use App\Service\VoyageService;
use App\Service\WaitlistService;
use App\Service\WeatherService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
        private readonly AdminController $adminController,
        private readonly ValidationService $validationService,
        private readonly WaitlistService $waitlistService,
        private readonly WeatherService $weatherService,
        private readonly CarbonFootprintService $carbonService,
        private readonly AiCancellationService $aiCancellationService,
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $bus,
        private readonly MailerService $mailerService,
        private readonly FlouciPaymentService $flouciPaymentService,
    ) {}

    #[Route('/api/weather', name: 'api_weather', methods: ['GET'])]
    public function apiWeather(Request $request): JsonResponse
    {
        $city = trim((string) $request->query->get('city', ''));
        if ($city === '') {
            return $this->json(null);
        }
        return $this->json($this->weatherService->getCurrentWeather($city));
    }

    #[Route('/voyages/{id}/reserve', name: 'travel_voyage_reserve', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function reserveVoyage(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $voyage = $this->voyageService->getVoyageById($id);
        if ($voyage === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        $offers = array_filter($this->offerService->getActiveOffers(), fn($o) => (int) $o['voyage_id'] === $id);
        $activeOffer = $offers ? array_values($offers)[0] : null;

        $isHighDemand    = $this->waitlistService->isHighDemand($id);
        $isOnWaitlist    = $this->waitlistService->isOnWaitlist($user['id'], $id);
        $activeCount     = $this->waitlistService->getActiveReservationCount($id);
        $loyaltyBalance  = $this->loyaltyPointsService->getBalance($user['id']);
        $canRedeem       = $this->loyaltyPointsService->canRedeem($user['id']);

        // Carbon footprint for this voyage destination
        $carbon = $this->carbonService->calculate($voyage['destination'] ?? '', 1);

        if ($request->isMethod('POST')) {
            $numberOfPeople  = (int) $request->request->get('number_of_people', 1);
            $usePoints       = $request->request->get('use_loyalty_points') === '1';

            $this->validationService->clearErrors();
            $this->validationService->validateNumber($numberOfPeople, 'number_of_people', 1, 20);

            if (!$this->validationService->isValid()) {
                foreach ($this->validationService->getErrors() as $fieldErrors) {
                    foreach ($fieldErrors as $err) {
                        $this->addFlash('error', $err);
                    }
                }
                return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
            }

            $voyagePrice   = (float) ($voyage['price'] ?? 0);
            $offerDiscount = $activeOffer ? ((float) $activeOffer['discount_percentage'] / 100) : 0;
            $loyaltyDiscount = ($usePoints && $canRedeem) ? 0.05 : 0;
            $totalPrice    = $numberOfPeople * $voyagePrice * (1 - $offerDiscount) * (1 - $loyaltyDiscount);

            // Deduct loyalty points before creating reservation
            if ($usePoints && $canRedeem) {
                $this->loyaltyPointsService->redeemDiscount($user['id']);
            }

            try {
                $created = $this->reservationService->createReservation(
                    $user['id'], $id,
                    $activeOffer ? (int) $activeOffer['id'] : null,
                    $numberOfPeople, $totalPrice
                );

                if ($created === null) {
                    throw new \Exception('Creation failed sorry');
                }

                try {
                    $userEmail = $user['email'] ?? null;
                    if ($userEmail) {
                        $this->mailerService->sendMailTo($userEmail);
                    }
                } catch (\Throwable $e) {
                    // don't block the user if email fails
                }

                // Redirect to Flouci payment
                $successUrl = $this->generateUrl('reservation_payment_success',
                    ['reservation_id' => $created['id']], UrlGeneratorInterface::ABSOLUTE_URL);
                $failUrl = $this->generateUrl('reservation_payment_cancel',
                    ['reservation_id' => $created['id']], UrlGeneratorInterface::ABSOLUTE_URL);

                $payment = $this->flouciPaymentService->createPayment(
                    $created['id'],
                    (float) $created['total_price'],
                    $successUrl,
                    $failUrl,
                );

                if ($payment === null || empty($payment['link'])) {
                    // Flouci unavailable — keep reservation PENDING, manual flow
                    $this->addFlash('warning', 'Reservation saved! Payment gateway unavailable — our team will contact you to complete payment.' . ($loyaltyDiscount > 0 ? ' 5% loyalty discount applied.' : ''));
                    return $this->redirectToRoute('account_reservation_detail', ['id' => $created['id']]);
                }

                $request->getSession()->set('flouci_payment_' . $created['id'], $payment['payment_id']);

                return $this->redirect($payment['link']);

            } catch (\Throwable $e) {
                $this->addFlash('error', 'Error: ' . $e->getMessage());
            }

            return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
        }

        return $this->render('travel/reserve.html.twig', [
            'active_nav'      => 'voyages',
            'voyage'          => $voyage,
            'offer'           => $activeOffer,
            'error'           => null,
            'success'         => null,
            'is_high_demand'  => $isHighDemand,
            'is_on_waitlist'  => $isOnWaitlist,
            'active_count'    => $activeCount,
            'loyalty_balance' => $loyaltyBalance,
            'can_redeem'      => $canRedeem,
            'carbon'          => $carbon,
        ]);
    }

    #[Route('/reservation/payment/success', name: 'reservation_payment_success', methods: ['GET'])]
    public function paymentSuccess(Request $request): Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser) {
            return $this->redirectToRoute('auth_login');
        }

        $reservationId = $request->query->getInt('reservation_id');
        $userId        = (int) $sessionUser['id'];

        $paymentId = $request->getSession()->get('flouci_payment_' . $reservationId);
        if (!$paymentId) {
            $this->addFlash('error', 'Payment session expired. If you completed payment, contact support with your reservation ID: ' . $reservationId);
            return $this->redirectToRoute('account_bookings');
        }

        $status = $this->flouciPaymentService->verifyPayment($paymentId);
        if ($status !== 'SUCCESS') {
            $this->addFlash('error', 'Payment could not be verified (status: ' . ($status ?? 'unknown') . '). If funds were deducted, contact support.');
            return $this->redirectToRoute('account_reservation_detail', ['id' => $reservationId]);
        }

        $request->getSession()->remove('flouci_payment_' . $reservationId);
        $this->reservationService->confirmReservation($reservationId, $userId, $paymentId);

        $this->addFlash('success', 'Payment confirmed! Your booking is now active. 🎉');
        return $this->redirectToRoute('account_reservation_detail', ['id' => $reservationId]);
    }

    #[Route('/reservation/{id}/pay-now', name: 'reservation_pay_now', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function payNow(Request $request, int $id): Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser) {
            return $this->redirectToRoute('auth_login');
        }

        $userId      = (int) $sessionUser['id'];
        $reservation = $this->reservationService->getReservationById($id, $userId);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        if ($reservation['status'] !== 'PENDING' || ($reservation['payment_status'] ?? '') === 'PAID') {
            $this->addFlash('error', 'This reservation does not require payment.');
            return $this->redirectToRoute('account_reservation_detail', ['id' => $id]);
        }

        $successUrl = $this->generateUrl('reservation_payment_success',
            ['reservation_id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        $failUrl = $this->generateUrl('reservation_payment_cancel',
            ['reservation_id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);

        $payment = $this->flouciPaymentService->createPayment(
            $id,
            (float) $reservation['total_price'],
            $successUrl,
            $failUrl,
        );

        if ($payment === null || empty($payment['link'])) {
            $this->addFlash('warning', 'Payment gateway is temporarily unavailable. Please try again later or contact support.');
            return $this->redirectToRoute('account_reservation_detail', ['id' => $id]);
        }

        $request->getSession()->set('flouci_payment_' . $id, $payment['payment_id']);

        return $this->redirect($payment['link']);
    }

    #[Route('/reservation/payment/cancel', name: 'reservation_payment_cancel', methods: ['GET'])]
    public function paymentCancel(Request $request): Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser) {
            return $this->redirectToRoute('auth_login');
        }

        $reservationId = $request->query->getInt('reservation_id');
        $userId        = (int) $sessionUser['id'];

        $request->getSession()->remove('flouci_payment_' . $reservationId);

        $this->addFlash('warning', 'Payment was cancelled. Your reservation is saved as pending — you can complete payment anytime.');

        $reservation = $reservationId
            ? $this->reservationService->getReservationById($reservationId, $userId)
            : null;
        $voyageId = $reservation['voyage_id'] ?? null;

        return $voyageId
            ? $this->redirectToRoute('travel_voyage_reserve', ['id' => $voyageId])
            : $this->redirectToRoute('travel_voyages');
    }

    #[Route('/account/bookings', name: 'account_bookings', methods: ['GET'])]
    public function accountBookings(Request $request): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $reservations   = $this->reservationService->getReservationsForUser($user['id']);
        $loyaltyBalance = $this->loyaltyPointsService->getBalance($user['id']);
        $canRedeem      = $this->loyaltyPointsService->canRedeem($user['id']);

        return $this->render('travel/bookings.html.twig', [
            'active_nav'      => 'account',
            'bookings'        => $reservations,
            'loyalty_balance' => $loyaltyBalance,
            'can_redeem'      => $canRedeem,
        ]);
    }

    #[Route('/admin/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function adminReservations(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }
        $status       = $request->query->get('status');
        $reservations = $this->reservationService->listAllReservations();
        if ($status) {
            $reservations = array_filter($reservations, fn($r) => $r['status'] === $status);
        }
        return $this->render('travel/admin_reservations.html.twig', [
            'active_nav'   => 'account',
            'reservations' => $reservations,
        ]);
    }

    #[Route('/admin/reservations/{id}/confirm', name: 'admin_reservation_confirm', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminConfirm(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $paymentReference = trim((string) $request->request->get('payment_reference', ''));

        if ($this->reservationService->confirmReservationAsAdmin($id, $paymentReference !== '' ? $paymentReference : null)) {
            $this->addFlash('success', 'Reservation confirmed successfully.');
        } else {
            $this->addFlash('error', 'Unable to confirm reservation.');
        }

        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/admin/reservations/{id}/cancel', name: 'admin_reservation_cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminCancel(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($this->reservationService->cancelReservationAsAdmin($id)) {
            $this->addFlash('success', 'Reservation cancelled successfully.');
        } else {
            $this->addFlash('error', 'Unable to cancel reservation.');
        }

        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/account/reservations/{id}/ticket', name: 'account_reservation_ticket', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function printTicket(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $isAdmin = $user['is_admin'] ?? false;
        $reservation = $isAdmin
            ? $this->reservationService->getReservationByIdAdmin($id)
            : $this->reservationService->getReservationById($id, $user['id']);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        if ($reservation['status'] !== 'CONFIRMED') {
            $this->addFlash('error', 'Ticket is only available for confirmed reservations.');
            return $this->redirectToRoute('account_reservation_detail', ['id' => $id]);
        }

        $carbon  = $this->carbonService->calculate($reservation['destination'] ?? '', (int) ($reservation['number_of_people'] ?? 1));
        $baseUrl = $this->resolveBaseUrl($request);

        return $this->render('travel/ticket_print.html.twig', [
            'reservation' => $reservation,
            'carbon'      => $carbon,
            'base_url'    => $baseUrl,
        ]);
    }

    #[Route('/account/reservations/{id}/ticket.pdf', name: 'account_reservation_ticket_pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function printTicketPdf(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $isAdmin = $user['is_admin'] ?? false;
        $reservation = $isAdmin
            ? $this->reservationService->getReservationByIdAdmin($id)
            : $this->reservationService->getReservationById($id, $user['id']);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        if ($reservation['status'] !== 'CONFIRMED') {
            $this->addFlash('error', 'PDF ticket is only available for confirmed reservations.');
            return $this->redirectToRoute('account_reservation_detail', ['id' => $id]);
        }

        $carbon = $this->carbonService->calculate(
            $reservation['destination'] ?? '',
            (int) ($reservation['number_of_people'] ?? 1)
        );

        $baseUrl = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = $request->getSchemeAndHttpHost();
        }
        $pdfUrl = $baseUrl . $this->generateUrl('account_reservation_ticket_pdf', ['id' => $id]);
        $qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($pdfUrl);

        $html = $this->renderView('travel/ticket_pdf.html.twig', [
            'reservation' => $reservation,
            'carbon'      => $carbon,
            'qr_url'      => $qrUrl,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="travagir-ticket-' . $id . '.pdf"',
        ]);
    }

    #[Route('/account/reservations/{id}/cancel-warning', name: 'account_reservation_cancel_warning', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function cancelWarning(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->json(['warning' => null]);
        }

        $reservation = $this->reservationService->getReservationById($id, $user['id']);
        if (!$reservation || $reservation['status'] !== 'CONFIRMED') {
            return $this->json(['warning' => null]);
        }

        // Enrich with voyage data so the AI gets meaningful context
        if (!empty($reservation['voyage_id']) && empty($reservation['voyage_title'])) {
            $voyage = $this->voyageService->getVoyageById($reservation['voyage_id']);
            if ($voyage) {
                $reservation['voyage_title'] = $voyage['title'] ?? 'your trip';
                $reservation['destination']  = $voyage['destination'] ?? '';
                $reservation['voyage_start'] = $voyage['start_date'] ?? null;
            }
        }

        $warning = $this->aiCancellationService->getWarning($reservation);
        return $this->json(['warning' => $warning]);
    }

    #[Route('/account/reservations/{id}', name: 'account_reservation_detail', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function accountReservationDetail(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $isAdmin = $user['is_admin'] ?? false;
        if ($isAdmin) {
            $reservation = $this->reservationService->getReservationByIdAdmin($id);
        } else {
            $reservation = $this->reservationService->getReservationById($id, $user['id']);
            if ($reservation) {
                $voyage = $this->voyageService->getVoyageById($reservation['voyage_id']);
                $reservation['voyage_title'] = $voyage ? $voyage['title'] : 'Unknown Voyage';
                $reservation['destination']  = $voyage ? $voyage['destination'] : 'Unknown';
                $reservation['voyage_start'] = $voyage ? $voyage['start_date'] : null;
                $reservation['voyage_end']   = $voyage ? $voyage['end_date'] : null;
            }
        }

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        $error   = null;
        $success = null;

        if ($request->isMethod('POST')) {
            if ($request->request->has('action_confirm')) {
                if ($this->adminController->ensureIsAdmin($request) !== null) {
                    return $this->adminController->ensureIsAdmin($request);
                }
                $paymentReference = trim((string) $request->request->get('payment_reference', ''));
                if ($this->reservationService->confirmReservationAsAdmin($id, $paymentReference !== '' ? $paymentReference : null)) {
                    $this->addFlash('success', 'Reservation confirmed successfully.');
                    return $this->redirectToRoute('admin_reservations');
                } else {
                    $error = 'Unable to confirm reservation. It may already be confirmed or cancelled.';
                }
            }

            if ($request->request->has('action_cancel')) {
                $voyageId = (int) ($reservation['voyage_id'] ?? 0);
                $cancelled = $isAdmin
                    ? $this->reservationService->cancelReservationAsAdmin($id)
                    : $this->reservationService->cancelReservation($id, $user['id']);

                if ($cancelled) {
                    // Notify next person on waitlist
                    $nextEntry = $this->waitlistService->getNextEntry($voyageId);
                    if ($nextEntry) {
                        $waitlistUser = $this->userRepository->find($nextEntry->getUserId());
                        if ($waitlistUser?->getTel()) {
                            $this->bus->dispatch(new SendSmsMessage(
                                $waitlistUser->getTel(),
                                sprintf(
                                    'Good news, %s! A spot just opened up for "%s". Log in now to secure your reservation before it\'s gone! – TravelAgency',
                                    $waitlistUser->getUsername() ?? 'Traveller',
                                    $reservation['voyage_title'] ?? 'your trip'
                                )
                            ));
                        }
                        $this->waitlistService->markNotified($nextEntry->getId());
                    }
                    $this->addFlash('success', 'Reservation successfully cancelled.');
                    return $this->redirectToRoute('account_bookings');
                } else {
                    $error = 'Unable to cancel reservation. It may already be processed or cancelled.';
                }
            }

            if ($request->request->has('action_refund')) {
                $reason = (string) $request->request->get('refund_reason', '');

                $this->validationService->clearErrors();
                $this->validationService->validateRequired(['refund_reason' => $reason], ['refund_reason']);
                $this->validationService->validateString($reason, 'refund_reason', 10, 500);

                if (!$this->validationService->isValid()) {
                    $errors = $this->validationService->getErrors();
                    $error  = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
                } else {
                    $eligibility = $this->reservationService->evaluateRefundEligibility($id, $user['id']);
                    if (!$eligibility['eligible']) {
                        $error = (string) ($eligibility['reason'] ?? 'Refund request is not eligible.');
                    } elseif ($this->reservationService->requestRefund($id, $user['id'], $reason)) {
                        $success = 'Refund request submitted and awaiting admin review.';
                    } else {
                        $error = 'Unable to submit refund request. Please review reservation status and try again.';
                    }
                }
            }
        }

        // Carbon footprint
        $carbon = $this->carbonService->calculate(
            $reservation['destination'] ?? '',
            (int) ($reservation['number_of_people'] ?? 1)
        );

        return $this->render('travel/reservation_detail.html.twig', [
            'active_nav'    => 'account',
            'reservation'   => $reservation,
            'error'         => $error,
            'success'       => $success,
            'is_admin_view' => $isAdmin,
            'carbon'        => $carbon,
            'base_url'      => $this->resolveBaseUrl($request),
        ]);
    }

    private function resolveBaseUrl(Request $request): string
    {
        $env = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        return $env !== '' ? $env : $request->getSchemeAndHttpHost();
    }
}
