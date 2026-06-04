<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use App\Repository\AdminRepository;
use App\Service\UserLoginService;
use Psr\Log\LoggerInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private UserLoginService $userLoginService,
        private AdminRepository $adminRepository,
        private LoggerInterface $logger
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                if (!$email) {
                    throw new \RuntimeException('Google did not return an email address for this account.');
                }

                // 1) On cherche l'utilisateur par email
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                // 2) S'il n'existe pas, on le crée
                if (!$user) {
                    // Fallback to the email local-part if Google returns no name,
                    // and trim to the username column length (varchar(50)).
                    $name = $googleUser->getName() ?: strstr($email, '@', true);
                    $name = mb_substr(trim((string) $name), 0, 50);

                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($name !== '' ? $name : 'user');
                    // Random password since the column is non-null and the user logs in via Google.
                    $user->setPassword(password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT));
                    $user->setCreatedAt(new \DateTime());

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                return $user;
            })
        );
    }

// src/Security/GoogleAuthenticator.php

public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
{
    /** @var User $user */
    $user = $token->getUser();

    // On transforme l'entité en tableau pour matcher le format de ton login classique
    // afin que tes templates Twig ne plantent pas.
    $admin = $this->adminRepository->findOneBy(['user' => $user]);
    $userData = [
        'id' => $user->getId(),
        'email' => $user->getEmail(),
        'username' => $user->getUsername(),
        'is_admin' => $admin !== null,
    ];

    // On remplit la session manuellement comme dans ton LoginController
    $request->getSession()->set('auth_user', $userData);

    // Enregistrement du log de connexion (comme tu le fais en classique)
    $this->userLoginService->recordLogin(
        (int) $user->getId(),
        'google',
        $request->getClientIp(),
        $request->headers->get('User-Agent')
    );

    // Redirection vers la page d'accueil de Travigir
    return new RedirectResponse($this->router->generate('travel_home'));
}

 public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    // Log the real cause (redirect_uri mismatch, DB error, etc.) so failures are diagnosable,
    // and show the user a friendly message instead of silently bouncing to the home page.
    $this->logger->error('Google authentication failed', [
        'message'  => $exception->getMessage(),
        'previous' => $exception->getPrevious()?->getMessage(),
    ]);

    $request->getSession()->getFlashBag()->add(
        'error',
        'Google sign-in failed. Please try again, or log in with your email and password.'
    );

    return new RedirectResponse($this->router->generate('auth_login'));
}
}
