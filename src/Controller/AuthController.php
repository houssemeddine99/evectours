<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\ValidationService;
use App\Service\UserLoginService;
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
        private readonly ValidationService $validationService,
        private readonly UserLoginService $userLoginService,
        private readonly LoggerInterface $logger
    ) {}

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

            // Use ValidationService for validation
            $this->validationService->validateLogin(['email' => $email, 'password' => $password]);

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                $error = implode(' ', array_map(fn($e) => implode(', ', $e), $errors));
            } else {
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

                        // Record successful login
                        $ipAddress = $request->getClientIp();
                        $userAgent = $request->headers->get('User-Agent');
                        $this->userLoginService->recordLogin(
                            $user['id'],
                            'email',
                            $ipAddress,
                            $userAgent
                        );

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
        }

        return $this->render('auth/login.html.twig', [
            'active_nav' => '',
            'email' => $email,
            'error' => $error,
        ]);
    }

    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        $sent  = false;
        $error = null;

        if ($request->isMethod('POST')) {
            $email = strtolower(trim((string) $request->request->get('email', '')));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!$this->authService->emailExists($email)) {
                // Don't reveal whether email exists — show success either way
                $sent = true;
            } else {
                $token = bin2hex(random_bytes(32));
                $request->getSession()->set('pwd_reset_token', $token);
                $request->getSession()->set('pwd_reset_email', $email);
                $request->getSession()->set('pwd_reset_expires', time() + 3600);
                $this->logger->info('Password reset requested', ['email' => $email]);
                $sent = true;
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'active_nav' => '',
            'sent'  => $sent,
            'error' => $error,
        ]);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request): Response
    {
        $token   = (string) $request->query->get('token', $request->request->get('token', ''));
        $session = $request->getSession();
        $error   = null;
        $success = false;

        $validToken   = $session->get('pwd_reset_token');
        $resetEmail   = $session->get('pwd_reset_email');
        $resetExpires = $session->get('pwd_reset_expires', 0);

        if (!$token || $token !== $validToken || time() > $resetExpires) {
            return $this->render('auth/reset_password.html.twig', [
                'active_nav' => '',
                'invalid'    => true,
                'token'      => '',
                'error'      => null,
                'success'    => false,
            ]);
        }

        if ($request->isMethod('POST')) {
            $password  = (string) $request->request->get('password', '');
            $password2 = (string) $request->request->get('password_confirm', '');

            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $password2) {
                $error = 'Passwords do not match.';
            } else {
                $user = $this->authService->getUserByEmail($resetEmail);
                if ($user) {
                    $this->authService->updateProfile(
                        $user['id'],
                        $user['username'],
                        $user['email'],
                        $user['tel'] ?? '',
                        $user['image_url'] ?? null,
                        $password
                    );
                    $session->remove('pwd_reset_token');
                    $session->remove('pwd_reset_email');
                    $session->remove('pwd_reset_expires');
                    $this->logger->info('Password reset completed', ['email' => $resetEmail]);
                    $success = true;
                } else {
                    $error = 'Account not found.';
                }
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'active_nav' => '',
            'invalid'    => false,
            'token'      => $token,
            'error'      => $error,
            'success'    => $success,
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
}
