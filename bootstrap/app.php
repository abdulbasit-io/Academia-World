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
        // Ensure API routes always return JSON responses, even for 500 errors
        $exceptions->render(function (Throwable $e, $request) {
            // Check if this is an API request
            if ($request->is('api/*') || $request->expectsJson()) {
                // For validation errors (422)
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Validation errors',
                        'errors' => $e->errors()
                    ], 422);
                }
                
                // For authentication errors (401)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'message' => 'Unauthenticated.'
                    ], 401);
                }
                
                // For authorization errors (403)
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'message' => 'This action is unauthorized.'
                    ], 403);
                }
                
                // For model not found errors (404)
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'message' => 'Resource not found.'
                    ], 404);
                }
                
                // For method not allowed errors (405)
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    return response()->json([
                        'message' => 'Method not allowed.'
                    ], 405);
                }
                
                // For not found errors (404)
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'message' => 'Endpoint not found.'
                    ], 404);
                }
                
                // For all other errors (500)
                $statusCode = 500;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                }
                
                // In production, don't expose detailed error information
                if (config('app.debug')) {
                    return response()->json([
                        'message' => 'Server Error',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], $statusCode);
                } else {
                    return response()->json([
                        'message' => 'Server Error',
                        'error' => 'Something went wrong. Please try again later.'
                    ], $statusCode);
                }
            }
        });
    })->create();
