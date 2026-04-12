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
use App\Service\UserLoginService;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private UserLoginService $userLoginService
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
                $name = $googleUser->getName();
           // ou getDisplayName() selon la version

                // 1) On cherche l'utilisateur par email
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                // 2) S'il n'existe pas, on le crée
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    // On utilise le nom Google comme username
                    $user->setUsername($name);

                    // On met un mot de passe aléatoire car le champ est souvent non-nul en DB
                    $user->setPassword(bin2hex(random_bytes(16)));

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
    $userData = [
        'id' => $user->getId(),
        'email' => $user->getEmail(),
        'username' => $user->getUsername(),
        'is_admin' => in_array('ROLE_ADMIN', $user->getRoles()),
    ];

    // On remplit la session manuellement comme dans ton LoginController
    $request->getSession()->set('auth_user', $userData);

    // Enregistrement du log de connexion (comme tu le fais en classique)
    $this->userLoginService->recordLogin(
        $user->getId(),
        'google',
        $request->getClientIp(),
        $request->headers->get('User-Agent')
    );

    // Redirection vers la page d'accueil de Travigir
    return new RedirectResponse($this->router->generate('travel_home'));
}

 public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    // Si la session 'auth_user' existe déjà, c'est que authenticate() a réussi 
    // avant que l'erreur ne survienne. On ignore l'erreur et on redirige.
    if ($request->getSession()->has('auth_user')) {
        return new RedirectResponse($this->router->generate('travel_home'));
    }

    // Sinon, on affiche l'erreur normalement
    return new Response("Erreur d'authentification : " . $exception->getMessage(), 403);
}
}
