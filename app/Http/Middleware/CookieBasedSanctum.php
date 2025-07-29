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
        // Only support cookie-based authentication
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
     * Handle cookie-based authentication
     */
    private function handleCookieToken(Request $request, Closure $next, string $token)
    {
        // Find the token in the database
        $accessToken = PersonalAccessToken::findToken($token);

        if ($accessToken && !$accessToken->cant('*')) {
            // Set the token for the request
            $request->setUserResolver(function () use ($accessToken) {
                return $accessToken->tokenable->withAccessToken($accessToken);
            });
            
            // Also set the user for Laravel's Auth system
            Auth::setUser($accessToken->tokenable);
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
