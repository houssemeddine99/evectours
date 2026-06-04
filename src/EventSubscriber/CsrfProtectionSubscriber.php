<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Application-wide CSRF protection for state-changing requests.
 *
 * The project handles requests with raw `$request->request->all()` rather than
 * Symfony Forms, so there is no per-form CSRF. This subscriber enforces a single
 * synchronizer token on every unsafe HTTP method (POST/PUT/PATCH/DELETE):
 *
 *   - HTML forms submit it in the `_token` field (auto-injected by base.html.twig JS)
 *   - fetch()/AJAX calls send it via the `X-CSRF-TOKEN` header (auto-injected too)
 *
 * The matching token is rendered once per page as `csrf_token('app')`.
 */
final class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    /** Single token id shared across the whole app (matches base.html.twig). */
    public const TOKEN_ID = 'app';

    private const FIELD_NAME = '_token';
    private const HEADER_NAME = 'X-CSRF-TOKEN';

    /** HTTP methods that do not mutate state and need no token. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    /** @return array<string, mixed> */
    public static function getSubscribedEvents(): array
    {
        // Run before the controller is resolved/executed.
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        // Skip Symfony internal routes (profiler, web debug toolbar, etc.).
        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || str_starts_with($route, '_')) {
            return;
        }

        $submitted = (string) (
            $request->request->get(self::FIELD_NAME)
            ?? $request->headers->get(self::HEADER_NAME, '')
        );

        if ($submitted !== '' && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $submitted))) {
            return;
        }

        $event->setResponse($this->buildFailureResponse($request));
    }

    private function buildFailureResponse(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $message = 'Invalid or missing security token. Please reload the page and try again.';

        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => $message], Response::HTTP_FORBIDDEN);
        }

        return new Response(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Security check failed</title></head><body style="font-family:sans-serif;padding:2rem">'
            . '<h1>Security check failed</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
            . '<p><a href="javascript:history.back()">Go back</a></p></body></html>',
            Response::HTTP_FORBIDDEN
        );
    }

    private function expectsJson(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');
        $contentType = (string) $request->headers->get('Content-Type', '');

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
