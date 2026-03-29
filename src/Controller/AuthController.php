<?php

namespace App\Controller;

use App\Service\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($request->getSession()->has('auth_user')) {
            $this->logger->info('Already authenticated user accessing login page, redirecting to home');
            return $this->redirectToRoute('travel_home');
        }

        $error = null;
        $email = '';

        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email', '');
            $password = (string) $request->request->get('password', '');

            $this->logger->info('Login form submitted', [
                'email' => $email,
                'ip' => $request->getClientIp()
            ]);

            // Check if email exists first
            $emailExists = $this->authService->emailExists($email);
            
            if (!$emailExists) {
                $this->logger->warning('Login failed - email not found', [
                    'email' => $email,
                    'ip' => $request->getClientIp()
                ]);
                $error = 'Email not found.';
            } else {
                $user = $this->authService->authenticate($email, $password);
                if ($user !== null) {
                    $this->logger->info('Creating user session after successful login', [
                        'email' => $email,
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'is_admin' => $user['is_admin'] ?? false,
                        'ip' => $request->getClientIp()
                    ]);
                    
                    $request->getSession()->set('auth_user', $user);

                    return $this->redirectToRoute('travel_home');
                }

                $this->logger->warning('Login failed - incorrect password', [
                    'email' => $email,
                    'ip' => $request->getClientIp()
                ]);
                $error = 'Incorrect password.';
            }
        }

        return $this->render('auth/login.html.twig', [
            'active_nav' => '',
            'email' => $email,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        $user = $request->getSession()->get('auth_user');
        
        if ($user) {
            $this->logger->info('User logging out', [
                'user_id' => $user['id'] ?? null,
                'username' => $user['username'] ?? null,
                'email' => $user['email'] ?? null,
                'ip' => $request->getClientIp()
            ]);
        }
        
        $request->getSession()->remove('auth_user');

        return $this->redirectToRoute('travel_home');
    }

    #[Route('/account/settings', name: 'account_settings', methods: ['GET', 'POST'])]
    public function accountSettings(Request $request): Response
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

        if ($request->isMethod('POST')) {
            $formData['username'] = (string) $request->request->get('username', '');
            $formData['email'] = (string) $request->request->get('email', '');
            $formData['tel'] = (string) $request->request->get('tel', '');
            $formData['image_url'] = (string) $request->request->get('image_url', '');
            $currentPassword = (string) $request->request->get('current_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (trim($formData['username']) === '' || trim($formData['email']) === '') {
                $error = 'Username and email are required.';
            } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
                $error = 'New password confirmation does not match.';
            } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPassword !== '' && $currentPassword === '') {
                $error = 'You must enter your current password to change password.';
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
        }

        return $this->render('auth/settings.html.twig', [
            'active_nav' => 'account',
            'formData' => $formData,
            'error' => $error,
            'success' => $success,
        ]);
    }

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

            if (empty($username) || empty($email) || empty($password)) {
                $this->logger->warning('Registration failed - missing required fields', [
                    'username' => $username,
                    'email' => $email,
                    'ip' => $request->getClientIp()
                ]);
                $error = 'All fields are required.';
            } elseif ($password !== $confirmPassword) {
                $this->logger->warning('Registration failed - password mismatch', [
                    'username' => $username,
                    'email' => $email,
                    'ip' => $request->getClientIp()
                ]);
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $this->logger->warning('Registration failed - password too short', [
                    'username' => $username,
                    'email' => $email,
                    'ip' => $request->getClientIp()
                ]);
                $error = 'Password must be at least 6 characters.';
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
}
