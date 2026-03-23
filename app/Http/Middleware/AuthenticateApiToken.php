<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.api.token');

        if (! $token) {
            return new JsonResponse(['message' => 'API token not configured.'], 500);
        }

        if ($request->bearerToken() !== $token) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
