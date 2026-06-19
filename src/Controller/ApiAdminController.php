<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
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
        private readonly UserRepository $userRepository,
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

        $users = 0;
        try {
            $users = $this->userRepository->count([]);
        } catch (\Throwable) {
        }

        return $this->json([
            'voyages'      => $voyages,
            'reservations' => count($reservations),
            'paid'         => count($paid),
            'pending'      => count($reservations) - count($paid),
            'revenue'      => round($revenue, 2),
            'users'        => $users,
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
            'date'           => $this->dateStr($r['reservation_date'] ?? null),
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

    #[Route('/voyages', name: 'api_v1_admin_voyages', methods: ['GET'])]
    public function voyages(Request $request): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $items = array_map(function (array $v): array {
            $images = is_array($v['image_url'] ?? null) ? array_values($v['image_url']) : [];
            return [
                'id'            => $v['id'] ?? null,
                'slug'          => $v['slug'] ?? '',
                'title'         => $v['title'] ?? '',
                'destination'   => $v['destination'] ?? '',
                'price'         => $v['price'] ?? null,
                'duration_days' => $v['duration_days'] ?? null,
                'image'         => $images[0] ?? null,
            ];
        }, $this->voyageService->getAllVoyagesForAdmin());
        return $this->json(['voyages' => $items]);
    }

    #[Route('/voyages/{id}', name: 'api_v1_admin_voyage_delete', methods: ['DELETE'])]
    public function deleteVoyage(Request $request, int $id): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $this->voyageService->deleteVoyage($id);
        return $this->json(['ok' => true]);
    }

    #[Route('/users', name: 'api_v1_admin_users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        if ($this->admin($request) === null) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $items = array_map(fn ($u): array => [
            'id'         => $u->getId(),
            'username'   => $u->getUsername(),
            'email'      => $u->getEmail(),
            'is_admin'   => $this->authService->isAdmin((int) $u->getId()),
            'created_at' => $u->getCreatedAt()?->format('Y-m-d'),
        ], $this->userRepository->findAll());
        return $this->json(['users' => $items]);
    }

    /** Coerce a date value (possibly a \DateTime) to a YYYY-MM-DD string. */
    private function dateStr(mixed $v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        return is_string($v) && $v !== '' ? substr($v, 0, 10) : null;
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
