<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\CurrencyService;
use App\Service\MailerService;
use App\Service\ValidationService;
use App\Service\UserLoginService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthController extends AbstractController
{
    /** Cache-key prefix for password-reset tokens (stored server-side so links work cross-device). */
    private const PWD_RESET_PREFIX = 'pwd_reset_';

    public function __construct(
        private readonly AuthService $authService,
        private readonly ValidationService $validationService,
        private readonly UserLoginService $userLoginService,
        private readonly CurrencyService $currencyService,
        private readonly MailerService $mailer,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/set-currency', name: 'set_currency', methods: ['POST'])]
    public function setCurrency(Request $request): JsonResponse
    {
        $currency = strtoupper((string) $request->request->get('currency', ''));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return new JsonResponse(['error' => 'Invalid currency'], 400);
        }
        $this->currencyService->setOverride($currency);
        return new JsonResponse(['currency' => $currency]);
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

                // Store the token server-side (hashed) so the reset link works on any device.
                $item = $this->cache->getItem(self::PWD_RESET_PREFIX . hash('sha256', $token));
                $item->set($email);
                $item->expiresAfter(3600);
                $this->cache->save($item);

                $resetUrl = $this->generateUrl(
                    'auth_reset_password',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                try {
                    $this->mailer->sendPasswordReset($email, $resetUrl);
                    $this->logger->info('Password reset email sent', ['email' => $email]);
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to send password reset email', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }

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
        $error   = null;
        $success = false;

        // Look the token up in the server-side store (works regardless of which device requested it).
        $cacheKey   = $token !== '' ? self::PWD_RESET_PREFIX . hash('sha256', $token) : '';
        $item       = $cacheKey !== '' ? $this->cache->getItem($cacheKey) : null;
        $resetEmail = ($item !== null && $item->isHit()) ? (string) $item->get() : null;

        if (!$token || $resetEmail === null) {
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
                    // Invalidate the token so it can't be reused.
                    $this->cache->deleteItem($cacheKey);
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
