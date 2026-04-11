<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\VoyageService;
use App\Service\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly VoyageService $voyageService,
        private readonly ValidationService $validationService,
        private readonly LoggerInterface $logger
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

    // ==================== GET PROFILE/SETTINGS ====================

    #[Route('/account/settings', name: 'account_settings', methods: ['GET'])]
    public function getSettings(Request $request): Response
    {
        $session = $request->getSession();
        $authUser = $session->get('auth_user');

        if (!$authUser || !isset($authUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }

        $formData = [
            'username' => $authUser['username'] ?? '',
            'email' => $authUser['email'] ?? '',
            'tel' => $authUser['tel'] ?? '',
            'image_url' => $authUser['image_url'] ?? '',
        ];

        return $this->render('auth/settings.html.twig', [
            'active_nav' => 'account',
            'formData' => $formData,
            'error' => null,
            'success' => null,
        ]);
    }

    // ==================== UPDATE PROFILE/SETTINGS ====================

    #[Route('/account/settings', name: 'account_settings_update', methods: ['POST'])]
    public function updateSettings(Request $request): Response
    {
        $session = $request->getSession();
        $authUser = $session->get('auth_user');

        if (!$authUser || !isset($authUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }

        $error = null;
        $success = null;
        $formData = [
            'username' => $authUser['username'] ?? '',
            'email' => $authUser['email'] ?? '',
            'tel' => $authUser['tel'] ?? '',
            'image_url' => $authUser['image_url'] ?? '',
        ];

        $formData['username'] = (string) $request->request->get('username', '');
        $formData['email'] = (string) $request->request->get('email', '');
        $formData['tel'] = (string) $request->request->get('tel', '');
        $formData['image_url'] = (string) $request->request->get('image_url', '');
        $currentPassword = (string) $request->request->get('current_password', '');
        $newPassword = (string) $request->request->get('new_password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');

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
        if ($newPassword !== '') {
            $this->validationService->validateString($newPassword, 'new_password', 6);
            if ($newPassword !== $confirmPassword) {
                $this->validationService->getErrors()['confirm_password'][] = 'New password confirmation does not match.';
            }
            if ($currentPassword === '') {
                $this->validationService->getErrors()['current_password'][] = 'You must enter your current password to change password.';
            }
        }

        if (!$this->validationService->isValid()) {
            $errors = $this->validationService->getErrors();
            $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
        } elseif ($currentPassword !== '' && !$this->authService->checkPasswordForUser($authUser['id'], $currentPassword)) {
            $error = 'Current password is incorrect.';
        } else {
            $updated = $this->authService->updateProfile(
                $authUser['id'],
                $formData['username'],
                $formData['email'],
                $formData['tel'],
                $formData['image_url'],
                $newPassword !== '' ? $newPassword : null,
                $currentPassword !== '' ? $currentPassword : null
            );

            if ($updated === null) {
                $error = 'Unable to save changes. Email may be in use, or validation failed.';
            } else {
                $success = 'Account settings updated successfully.';
                $updated['is_admin'] = $this->authService->isAdmin($authUser['id']);
                $session->set('auth_user', $updated);
            }
        }

        return $this->render('auth/settings.html.twig', [
            'active_nav' => 'account',
            'formData' => $formData,
            'error' => $error,
            'success' => $success,
        ]);
    }

    // ==================== DELETE ACCOUNT ====================

    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function deleteAccount(Request $request): Response
    {
        $session = $request->getSession();
        $authUser = $session->get('auth_user');

        if (!$authUser || !isset($authUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }

        $userId = $authUser['id'];

        // Delete the user account
        $deleted = $this->authService->deleteUser($userId);

        if ($deleted) {
            $this->logger->info('User account deleted', ['user_id' => $userId]);
            // Clear session and redirect to home
            $session->clear();
            $this->addFlash('success', 'Your account has been deleted.');
        } else {
            $this->logger->warning('Failed to delete user account', ['user_id' => $userId]);
            $this->addFlash('error', 'Unable to delete account. Please try again.');
            return $this->redirectToRoute('account_settings');
        }

        return $this->redirectToRoute('travel_home');
    }

    // ==================== REGISTER ====================

    #[Route('/register', name: 'auth_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($request->getSession()->has('auth_user')) {
            $this->logger->info('Already authenticated user accessing register page, redirecting to home');
            return $this->redirectToRoute('travel_home');
        }

        $error = null;
        $username = '';
        $email = '';

        if ($request->isMethod('POST')) {
            $username = (string) $request->request->get('username', '');
            $email = (string) $request->request->get('email', '');
            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            $this->logger->info('Registration form submitted', [
                'username' => $username,
                'email' => $email,
                
                'ip' => $request->getClientIp()
            ]);

            // Use ValidationService for validation
            $this->validationService->validateUserRegistration([
                'username' => $username,
                'email' => $email,
                'password' => $password,
              
            ]);

            // Check password match
            if ($password !== $confirmPassword) {
                $this->validationService->getErrors()['confirm_password'][] = 'Passwords do not match.';
            }

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
                $this->logger->warning('Registration failed - validation error', [
                    'username' => $username,
                    'email' => $email,
                    'errors' => $errors,
                    'ip' => $request->getClientIp()
                ]);
            } else {
                try {
                    $user = $this->authService->register($username, $email, $password);
                    if ($user !== null) {
                        $this->logger->info('User registered successfully', [
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'ip' => $request->getClientIp()
                        ]);
                        $request->getSession()->set('auth_user', $user);
                        return $this->redirectToRoute('travel_home');
                    }
                    $this->logger->warning('Registration failed - email already exists', [
                        'username' => $username,
                        'email' => $email,
                        'ip' => $request->getClientIp()
                    ]);
                    $error = 'Email already exists.';
                } catch (\Throwable $e) {
                    $this->logger->error('Registration failed - exception occurred', [
                        'username' => $username,
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                        'ip' => $request->getClientIp()
                    ]);
                    $error = 'Registration failed. Please try again.';
                }
            }
        }

        return $this->render('auth/register.html.twig', [
            'active_nav' => '',
            'username' => $username,
            'email' => $email,
            'error' => $error,
        ]);
    }

    // ==================== FAVORITES ====================

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
}