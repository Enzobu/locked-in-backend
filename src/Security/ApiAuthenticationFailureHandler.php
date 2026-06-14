<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Wraps the Lexik JWT failure handler so that throttled logins return a proper
 * HTTP 429 instead of a generic 401. Everything else is delegated unchanged.
 */
final class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_failure')]
        private readonly AuthenticationFailureHandlerInterface $decorated,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['code' => Response::HTTP_TOO_MANY_REQUESTS, 'message' => 'Too many failed login attempts. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return $this->decorated->onAuthenticationFailure($request, $exception);
    }
}
