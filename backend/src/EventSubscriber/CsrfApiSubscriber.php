<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfApiSubscriber implements EventSubscriberInterface
{
    private const string CSRF_HEADER = 'X-CSRF-Token';
    private const string CSRF_TOKEN_ID = 'api';
    private const array STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Only apply to /api/ routes with state-changing methods
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        if (!in_array($method, self::STATE_CHANGING_METHODS, true)) {
            return;
        }

        // Skip if the request has a valid session-based authentication
        // but check the CSRF header for browser-based API calls
        $contentType = $request->headers->get('Content-Type', '');

        // If this is a JSON API request (not from a form), verify the CSRF token header
        // Requests with custom headers (like X-CSRF-Token) cannot be made cross-origin
        // without CORS pre-flight, which provides CSRF protection.
        // We verify the token if provided, or accept the request if the custom header is present
        // (even with an empty value) since its presence proves same-origin.
        $csrfToken = $request->headers->get(self::CSRF_HEADER);

        if ($csrfToken === null) {
            // No CSRF header at all: check if this is a JSON request with XMLHttpRequest
            // XMLHttpRequest cannot be sent cross-origin without CORS preflight
            $xRequestedWith = $request->headers->get('X-Requested-With');
            if ($xRequestedWith === 'XMLHttpRequest') {
                return;
            }

            // For non-XHR requests without CSRF token, reject
            $event->setResponse(new JsonResponse([
                'error' => [
                    'code' => 'csrf_token_missing',
                    'message' => 'CSRF token is required for state-changing API requests. Include X-CSRF-Token header.',
                ],
            ], 403));

            return;
        }

        // If a token is provided, validate it
        $token = new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $event->setResponse(new JsonResponse([
                'error' => [
                    'code' => 'csrf_token_invalid',
                    'message' => 'Invalid CSRF token.',
                ],
            ], 403));
        }
    }
}
