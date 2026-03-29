<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly VoyageService $voyageService
    ) {
    }

    private function ensureAuthenticated(Request $request): ?Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        return null;
    }

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

    #[Route('/account/favorites', name: 'account_favorites', methods: ['GET'])]
    public function accountFavorites(Request $request): Response
    {
        if ($this->ensureAuthenticated($request) !== null) {
            return $this->redirectToRoute('auth_login');
        }

        $voyages = $this->voyageService->getFeaturedVoyages(3);

        return $this->render('travel/favorites.html.twig', [
            'active_nav' => 'account',
            'favorites' => $voyages,
        ]);
    }

    #[Route('/account/users', name: 'account_users', methods: ['GET'])]
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

    #[Route('/account/users/new', name: 'account_users_new', methods: ['GET', 'POST'])]
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

            if (trim($formData['username']) === '' || trim($formData['email']) === '' || trim($formData['password']) === '') {
                $error = 'Username, email and password are required.';
            } elseif ($formData['password'] !== $formData['confirm_password']) {
                $error = 'Passwords do not match.';
            } elseif (strlen($formData['password']) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $created = $this->authService->register(
                    $formData['username'],
                    $formData['email'],
                    $formData['password']
                );

                if ($created === null) {
                    $error = 'Unable to create user. Email may already exist.';
                } else {
                    return $this->redirectToRoute('account_users');
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

    #[Route('/account/users/{id}', name: 'account_users_view', methods: ['GET'])]
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

    #[Route('/account/users/{id}/edit', name: 'account_users_edit', methods: ['GET', 'POST'])]
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

            if (trim($formData['username']) === '' || trim($formData['email']) === '') {
                $error = 'Username and email are required.';
            } elseif ($formData['new_password'] !== '' && $formData['new_password'] !== $formData['confirm_password']) {
                $error = 'New password confirmation does not match.';
            } elseif ($formData['new_password'] !== '' && strlen($formData['new_password']) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($formData['new_password'] !== '' && $formData['current_password'] === '') {
                $error = 'Current password is required to set a new password.';
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
                    return $this->redirectToRoute('account_users_view', ['id' => $id]);
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

    #[Route('/account/users/{id}/delete', name: 'account_users_delete', methods: ['POST'])]
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

        return $this->redirectToRoute('account_users');
    }
}
