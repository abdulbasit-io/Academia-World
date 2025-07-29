<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CookieTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // First check for Authorization header (for API clients)
        if ($request->hasHeader('Authorization')) {
            return $next($request);
        }

        // Then check for token in cookie
        $token = $request->cookie('auth_token');
        
        if ($token) {
            // Add the token to the Authorization header for Sanctum to process
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
