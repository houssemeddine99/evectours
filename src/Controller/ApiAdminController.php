<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\ReservationService;
use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Admin-only JSON API for the mobile app (bearer token + is_admin).
 */
#[Route('/api/v1/admin')]
class ApiAdminController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ReservationService $reservationService,
        private readonly VoyageService $voyageService,
    ) {
    }

    #[Route('/stats', name: 'api_v1_admin_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $reservations = $this->reservationService->listAllReservations();
        $paid = array_filter($reservations, fn ($r) => strtoupper((string) ($r['payment_status'] ?? '')) === 'PAID');
        $revenue = array_sum(array_map(fn ($r) => (float) ($r['total_price'] ?? 0), $paid));
        $voyages = count($this->voyageService->getAllActiveVoyages());

        return $this->json([
            'voyages'      => $voyages,
            'reservations' => count($reservations),
            'paid'         => count($paid),
            'pending'      => count($reservations) - count($paid),
            'revenue'      => round($revenue, 2),
        ]);
    }

    #[Route('/reservations', name: 'api_v1_admin_reservations', methods: ['GET'])]
    public function reservations(Request $request): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $items = array_map(fn (array $r): array => [
            'id'             => $r['id'] ?? null,
            'voyage_title'   => $r['voyage_title'] ?? null,
            'destination'    => $r['destination'] ?? null,
            'customer'       => $r['user_name'] ?? null,
            'email'          => $r['user_email'] ?? null,
            'people'         => $r['number_of_people'] ?? null,
            'total_price'    => $r['total_price'] ?? null,
            'status'         => $r['status'] ?? null,
            'payment_status' => $r['payment_status'] ?? null,
            'date'           => $r['reservation_date'] ?? null,
        ], $this->reservationService->listAllReservations());

        return $this->json(['reservations' => $items]);
    }

    #[Route('/reservations/{id}/confirm', name: 'api_v1_admin_res_confirm', methods: ['POST'])]
    public function confirm(Request $request, int $id): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $this->reservationService->confirmReservationAsAdmin($id);
        return $this->json(['ok' => true]);
    }

    #[Route('/reservations/{id}/cancel', name: 'api_v1_admin_res_cancel', methods: ['POST'])]
    public function cancel(Request $request, int $id): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $this->reservationService->cancelReservationAsAdmin($id);
        return $this->json(['ok' => true]);
    }

    /** @return array<string,mixed>|null current user iff admin */
    private function admin(Request $request): ?array
    {
        $auth = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        if ($token === '') {
            return null;
        }
        $user = $this->authService->getUserByToken($token);
        if ($user === null || ($user['is_admin'] ?? false) !== true) {
            return null;
        }
        return $user;
    }
}
