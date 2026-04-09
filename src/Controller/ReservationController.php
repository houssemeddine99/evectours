<?php

namespace App\Controller;

use App\Service\ReservationService;
use App\Service\VoyageService;
use App\Service\OfferService;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
        private readonly AdminController $adminController,
        private readonly ValidationService $validationService
    ) {}

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

   

        if ($request->isMethod('POST')) {
            $numberOfPeople = (int) $request->request->get('number_of_people', 1);

            // Use ValidationService for validation
            $this->validationService->clearErrors();
            $this->validationService->validateNumber($numberOfPeople, 'number_of_people', 1, 20);

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $err) {
                        $this->addFlash('error', $err);
                    }
                }
                return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
            }

            $voyagePrice = (float) ($voyage['price'] ?? 0);
            $discount = $activeOffer ? ((float) $activeOffer['discount_percentage'] / 100) : 0;
            $totalPrice = $numberOfPeople * $voyagePrice * (1 - $discount);

            try {
                $created = $this->reservationService->createReservation(
                    $user['id'],
                    $id,
                    $activeOffer ? (int) $activeOffer['id'] : null,
                    $numberOfPeople,
                    $totalPrice
                );

                if ($created === null) {
                    throw new \Exception('Creation failed');
                }

                $this->addFlash('success', 'Reservation created successfully');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
        }

        return $this->render('travel/reserve.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
            'offer' => $activeOffer,
              'error' => null,  // Add this
    'success' => null, // Add this
        ]);
    }

    #[Route('/account/bookings', name: 'account_bookings', methods: ['GET'])]
    public function accountBookings(Request $request): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $reservations = $this->reservationService->getReservationsForUser($user['id']);

        return $this->render('travel/bookings.html.twig', [
            'active_nav' => 'account',
            'bookings' => $reservations,
        ]);
    }

    #[Route('/admin/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function adminReservations(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }
$status = $request->query->get('status');
        $reservations = $this->reservationService->listAllReservations();
if ($status) {
    $reservations = array_filter($reservations, fn($r) => $r['status'] === $status);
}
        return $this->render('travel/admin_reservations.html.twig', [
            'active_nav' => 'account',
            'reservations' => $reservations,
        ]);
    }

    #[Route('/admin/reservations/{id}/confirm', name: 'admin_reservation_confirm', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminConfirm(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($this->reservationService->confirmReservationAsAdmin($id)) {
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

    #[Route('/account/reservations/{id}', name: 'account_reservation_detail', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function accountReservationDetail(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        // Admin can view any reservation, regular users can only view their own
        $isAdmin = $user['is_admin'] ?? false;
        if ($isAdmin) {
            $reservation = $this->reservationService->getReservationByIdAdmin($id);
        } else {
            $reservation = $this->reservationService->getReservationById($id, $user['id']);
        }

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            if ($request->request->has('action_confirm')) {
                if ($this->reservationService->confirmReservation($id, $user['id'])) {
                    $this->addFlash('success', 'Reservation confirmed successfully. Enjoy your trip!');
                    return $this->redirectToRoute('account_bookings');
                } else {
                    $error = 'Unable to confirm reservation. It may already be confirmed, cancelled, or invalid.';
                }
            }

            if ($request->request->has('action_cancel')) {
                if ($this->reservationService->cancelReservation($id, $user['id'])) {
                    $this->addFlash('success', 'Reservation successfully cancelled.');
                    return $this->redirectToRoute('account_bookings');
                } else {
                    $error = 'Unable to cancel reservation. It may already be processed or cancelled.';
                }
            }

            if ($request->request->has('action_refund')) {
                $reason = (string) $request->request->get('refund_reason', '');

                // Use ValidationService for refund reason validation
                $this->validationService->clearErrors();
                $this->validationService->validateRequired(['refund_reason' => $reason], ['refund_reason']);
                $this->validationService->validateString($reason, 'refund_reason', 10, 500);

                if (!$this->validationService->isValid()) {
                    $errors = $this->validationService->getErrors();
                    $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
                } else {
                    if ($this->reservationService->requestRefund($id, $user['id'], $reason)) {
                        $success = 'Refund request submitted and awaiting admin review.';
                    } else {
                        $error = 'Unable to submit refund request. Please review reservation status and try again.';
                    }
                }
            }
        }

        return $this->render('travel/reservation_detail.html.twig', [
            'active_nav' => 'account',
            'reservation' => $reservation,
            'error' => $error,
            'success' => $success,
            'is_admin_view' => $isAdmin,
        ]);
    }
}