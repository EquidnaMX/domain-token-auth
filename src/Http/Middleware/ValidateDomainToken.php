<?php

namespace Equidna\DomainTokenAuth\Http\Middleware;

use Closure;
use Equidna\DomainTokenAuth\Exceptions\DomainTokenException;
use Equidna\DomainTokenAuth\Services\DomainTokenManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ValidateDomainToken
{
    public function __construct(private readonly DomainTokenManager $tokenManager) {}

    public function handle(Request $request, Closure $next, string $domain): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return new JsonResponse(['message' => 'Token not found.'], 401);
        }

        try {
            $authenticated = $this->tokenManager->authenticate($bearerToken, $domain);
        } catch (DomainTokenException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 401);
        }

        $request->attributes->set(DomainTokenManager::requestContextKey(), $authenticated);

        return $next($request);
    }
}
