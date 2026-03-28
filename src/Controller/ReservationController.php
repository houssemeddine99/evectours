<?php

namespace App\Controller;

use App\Service\ReservationService;
use App\Service\VoyageService;
use App\Service\OfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService
    ) {
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

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $numberOfPeople = max(1, (int) $request->request->get('number_of_people', 1));
            $reason = (string) $request->request->get('special_requests', '');

            $voyagePrice = (float) ($voyage['price'] ?? 0);
            $discount = $activeOffer ? ((float) $activeOffer['discount_percentage'] / 100) : 0;
            $totalPrice = $numberOfPeople * $voyagePrice * (1 - $discount);

            $created = $this->reservationService->createReservation(
                $user['id'],
                $id,
                $activeOffer ? (int) $activeOffer['id'] : null,
                $numberOfPeople,
                $totalPrice
            );

            if ($created === null) {
                $error = 'Failed to create reservation. Please check your data and try again.';
            } else {
                $success = 'Reservation created and pending admin decision. You can cancel anytime from your reservations list.';
            }
        }

        return $this->render('travel/reserve.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
            'offer' => $activeOffer,
            'error' => $error,
            'success' => $success,
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

    #[Route('/account/reservations/{id}', name: 'account_reservation_detail', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function accountReservationDetail(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $reservation = $this->reservationService->getReservationById($id, $user['id']);
        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            if ($request->request->has('action_cancel')) {
                if ($this->reservationService->cancelReservation($id, $user['id'])) {
                    $success = 'Reservation successfully cancelled.';
                    $reservation['status'] = 'CANCELLED';
                } else {
                    $error = 'Unable to cancel reservation. It may already be processed or cancelled.';
                }
            }

            if ($request->request->has('action_refund')) {
                $reason = (string) $request->request->get('refund_reason', '');
                if (trim($reason) === '') {
                    $error = 'Refund reason is required.';
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
        ]);
    }
}