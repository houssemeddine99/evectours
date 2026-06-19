<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Token-based auth + account for the mobile app.
 * The app sends a JSON body and a Bearer token (in Authorization header).
 */
#[Route('/api/v1/auth')]
class ApiAuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ReservationService $reservationService,
    ) {
    }

    #[Route('/register', name: 'api_v1_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $d = $this->body($request);
        $username = trim((string) ($d['username'] ?? ''));
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            return $this->json(['error' => 'Username, email and password are required.'], 422);
        }
        if (strlen($password) < 6) {
            return $this->json(['error' => 'Password must be at least 6 characters.'], 422);
        }
        if ($this->authService->emailExists($email)) {
            return $this->json(['error' => 'An account with this email already exists.'], 409);
        }

        $user = $this->authService->register($username, $email, $password);
        if ($user === null) {
            return $this->json(['error' => 'Could not create the account. Please try again.'], 400);
        }

        $token = $this->authService->issueTokenForUser((int) $user['id']);
        return $this->json([
            'token' => $token,
            'user' => $this->authService->getUserById((int) $user['id']),
        ], 201);
    }

    #[Route('/login', name: 'api_v1_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $d = $this->body($request);
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'Email and password are required.'], 422);
        }

        $user = $this->authService->authenticate($email, $password);
        if ($user === null) {
            return $this->json(['error' => 'Invalid email or password.'], 401);
        }

        $token = $this->authService->issueTokenForUser((int) $user['id']);
        return $this->json(['token' => $token, 'user' => $user]);
    }

    #[Route('/account', name: 'api_v1_account', methods: ['GET'])]
    public function account(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->json(['error' => 'Not authenticated.'], 401);
        }

        $bookings = array_map(function (array $r): array {
            return [
                'id'             => $r['id'] ?? null,
                'reference'      => $r['payment_reference'] ?? null,
                'voyage_title'   => $r['voyage_title'] ?? null,
                'destination'    => $r['destination'] ?? null,
                'people'         => $r['number_of_people'] ?? null,
                'total_price'    => $r['total_price'] ?? null,
                'status'         => $r['status'] ?? null,
                'payment_status' => $r['payment_status'] ?? null,
                'date'           => $r['reservation_date'] ?? null,
            ];
        }, $this->reservationService->getReservationsForUser((int) $user['id']));

        return $this->json(['user' => $user, 'bookings' => $bookings]);
    }

    #[Route('/logout', name: 'api_v1_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // Token is dropped client-side; invalidating server-side is optional.
        return $this->json(['ok' => true]);
    }

    // ── helpers ──────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $json = json_decode($request->getContent(), true);
        if (is_array($json)) {
            return $json;
        }
        return $request->request->all();
    }

    /** @return array<string,mixed>|null */
    private function currentUser(Request $request): ?array
    {
        $auth = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        if ($token === '') {
            return null;
        }
        return $this->authService->getUserByToken($token);
    }
}
