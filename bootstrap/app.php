<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\CookieBasedSanctum::class,
        ]);
        
        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'log.api' => \App\Http\Middleware\LogApiRequests::class,
            'cookie.auth' => \App\Http\Middleware\CookieBasedSanctum::class,
        ]);
        
        // Enable CORS for API routes
        $middleware->group('api', [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\LogApiRequests::class, // Add API logging
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
