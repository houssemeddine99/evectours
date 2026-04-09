<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\ReservationService;
use App\Service\VoyageService;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ReservationService $reservationService,
        private readonly VoyageService $voyageService,
        private readonly ValidationService $validationService
    ) {
    }

    // ==================== ADMIN AUTHORIZATION ====================

    /**
     * Ensure the user is authenticated, redirect if not
     * @return null|Response Returns null if authorized, redirects if not
     */
    private function ensureAuthenticated(Request $request): ?Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        return null;
    }

    /**
     * Ensure the user is an admin, redirect if not
     * @return null|Response Returns null if authorized, redirects if not
     */
    private function ensureAdmin(Request $request): ?Response
    {
        $notAuth = $this->ensureAuthenticated($request);
        if ($notAuth !== null) {
            return $notAuth;
        }

        $authUser = $request->getSession()->get('auth_user');
        $isAdmin = $authUser['is_admin'] ?? false;

        if (!$isAdmin && !$this->authService->isAdmin((int) ($authUser['id'] ?? 0))) {
            $this->addFlash('error', 'Admin access required.');
            return $this->redirectToRoute('travel_home');
        }

        return null;
    }

    /**
     * Public method to ensure user is admin - can be called from other controllers
     * @return null|Response Returns null if authorized, redirects if not
     */
    public function ensureIsAdmin(Request $request): ?Response
    {
        return $this->ensureAdmin($request);
    }

    // ==================== ADMIN DASHBOARD ====================

    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        // Get statistics for dashboard
        $users = $this->authService->listUsers();
        $reservations = $this->reservationService->listAllReservations();
        $voyages = $this->voyageService->getAllVoyages();

        // Calculate statistics
        $totalUsers = count($users);
        $totalReservations = count($reservations);
        $pendingReservations = count(array_filter($reservations, fn($r) => $r['status'] === 'PENDING'));
        $confirmedReservations = count(array_filter($reservations, fn($r) => $r['status'] === 'CONFIRMED'));
        $cancelledReservations = count(array_filter($reservations, fn($r) => $r['status'] === 'CANCELLED'));
        $totalRevenue = array_sum(array_map(fn($r) => (float) ($r['total_price'] ?? 0), $reservations));
        $totalVoyages = count($voyages);

        // Get recent reservations (last 5)
        $recentReservations = array_slice($reservations, 0, 5);

        // Get recent users (last 5)
        $recentUsers = array_slice($users, 0, 5);

        return $this->render('admin/dashboard.html.twig', [
            'active_nav' => 'account',
            'stats' => [
                'total_users' => $totalUsers,
                'total_reservations' => $totalReservations,
                'pending_reservations' => $pendingReservations,
                'confirmed_reservations' => $confirmedReservations,
                'cancelled_reservations' => $cancelledReservations,
                'total_revenue' => $totalRevenue,
                'total_voyages' => $totalVoyages,
            ],
            'recent_reservations' => $recentReservations,
            'recent_users' => $recentUsers,
        ]);
    }

    // ==================== USER MANAGEMENT ====================

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        $users = $this->authService->listUsers();

        return $this->render('user/index.html.twig', [
            'active_nav' => 'account',
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        $error = null;
        $formData = [
            'username' => '',
            'email' => '',
            'tel' => '',
            'image_url' => '',
            'password' => '',
            'confirm_password' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData['username'] = (string) $request->request->get('username', '');
            $formData['email'] = (string) $request->request->get('email', '');
            $formData['tel'] = (string) $request->request->get('tel', '');
            $formData['image_url'] = (string) $request->request->get('image_url', '');
            $formData['password'] = (string) $request->request->get('password', '');
            $formData['confirm_password'] = (string) $request->request->get('confirm_password', '');

            // Use ValidationService for validation
            $this->validationService->validateUserRegistration($formData);
            
            // Check password match
            if ($formData['password'] !== $formData['confirm_password']) {
                $this->validationService->getErrors()['confirm_password'][] = 'Passwords do not match.';
            }

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
            } else {
                $created = $this->authService->register(
                    $formData['username'],
                    $formData['email'],
                    $formData['password']
                );

                if ($created === null) {
                    $error = 'Unable to create user. Email may already exist.';
                } else {
                    return $this->redirectToRoute('admin_users');
                }
            }
        }

        return $this->render('user/form.html.twig', [
            'active_nav' => 'account',
            'is_edit' => false,
            'error' => $error,
            'formData' => $formData,
        ]);
    }

    #[Route('/users/{id}', name: 'admin_users_view', methods: ['GET'])]
    public function view(Request $request, int $id): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        $user = $this->authService->getUserById($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found.');
        }

        return $this->render('user/view.html.twig', [
            'active_nav' => 'account',
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        $currentUser = $this->authService->getUserById($id);
        if ($currentUser === null) {
            throw $this->createNotFoundException('User not found.');
        }

        $error = null;
        $formData = [
            'username' => $currentUser['username'] ?? '',
            'email' => $currentUser['email'] ?? '',
            'tel' => $currentUser['tel'] ?? '',
            'image_url' => $currentUser['image_url'] ?? '',
            'current_password' => '',
            'new_password' => '',
            'confirm_password' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData['username'] = (string) $request->request->get('username', '');
            $formData['email'] = (string) $request->request->get('email', '');
            $formData['tel'] = (string) $request->request->get('tel', '');
            $formData['image_url'] = (string) $request->request->get('image_url', '');
            $formData['current_password'] = (string) $request->request->get('current_password', '');
            $formData['new_password'] = (string) $request->request->get('new_password', '');
            $formData['confirm_password'] = (string) $request->request->get('confirm_password', '');

            // Use ValidationService for validation
            $this->validationService->clearErrors();
            $this->validationService->validateRequired($formData, ['username', 'email']);
            $this->validationService->validateEmail($formData['email']);
            $this->validationService->validateString($formData['username'], 'username', 3, 50);
            $this->validationService->validateAlphaNum($formData['username'], 'username');
            
            // Phone validation (optional field)
            if (!empty($formData['tel'])) {
                $this->validationService->validatePhone($formData['tel']);
            }

            // Password validation (optional - only if new password is provided)
            if ($formData['new_password'] !== '') {
                $this->validationService->validateString($formData['new_password'], 'new_password', 6);
                if ($formData['new_password'] !== $formData['confirm_password']) {
                    $this->validationService->getErrors()['confirm_password'][] = 'New password confirmation does not match.';
                }
                if ($formData['current_password'] === '') {
                    $this->validationService->getErrors()['current_password'][] = 'Current password is required to set a new password.';
                }
            }

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
            } else {
                $updated = $this->authService->updateProfile(
                    $id,
                    $formData['username'],
                    $formData['email'],
                    $formData['tel'],
                    $formData['image_url'],
                    $formData['new_password'] !== '' ? $formData['new_password'] : null,
                    $formData['current_password'] !== '' ? $formData['current_password'] : null
                );

                if ($updated === null) {
                    $error = 'Unable to update user. Email may already be used or credentials are invalid.';
                } else {
                    return $this->redirectToRoute('admin_users_view', ['id' => $id]);
                }
            }
        }

        return $this->render('user/form.html.twig', [
            'active_nav' => 'account',
            'is_edit' => true,
            'error' => $error,
            'formData' => $formData,
            'userId' => $id,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if ($this->ensureAdmin($request) !== null) {
            return $this->ensureAdmin($request);
        }

        if (!$this->authService->deleteUser($id)) {
            $this->addFlash('error', 'Unable to delete user.');
        } else {
            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_users');
    }
}