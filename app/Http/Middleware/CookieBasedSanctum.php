<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class CookieBasedSanctum
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // First, try the standard Sanctum authentication (Bearer token)
        if ($request->bearerToken()) {
            return $this->handleBearerToken($request, $next);
        }

        // If no bearer token, try cookie-based authentication
        if ($token = $request->cookie('academia_world_token')) {
            return $this->handleCookieToken($request, $next, $token);
        }

        // If this is a stateful domain, use session-based auth
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            return $next($request);
        }

        // No authentication found
        return $next($request);
    }

    /**
     * Handle Bearer token authentication
     */
    protected function handleBearerToken(Request $request, Closure $next)
    {
        $token = PersonalAccessToken::findToken($request->bearerToken());

        if ($token && $this->isValidToken($token)) {
            Auth::guard('sanctum')->setUser($token->tokenable);
            $request->attributes->set('sanctum_token', $token);
        }

        return $next($request);
    }

    /**
     * Handle Cookie token authentication
     */
    protected function handleCookieToken(Request $request, Closure $next, string $tokenString)
    {
        $token = PersonalAccessToken::findToken($tokenString);

        if ($token && $this->isValidToken($token)) {
            Auth::guard('sanctum')->setUser($token->tokenable);
            $request->attributes->set('sanctum_token', $token);
        }

        return $next($request);
    }

    /**
     * Check if token is valid
     */
    protected function isValidToken(PersonalAccessToken $token): bool
    {
        // Check if token is expired
        if ($token->expires_at && $token->expires_at->isPast()) {
            return false;
        }

        // Check if token abilities match
        return true;
    }
}
