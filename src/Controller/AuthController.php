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
