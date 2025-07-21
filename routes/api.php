<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ResourceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('auth/resend-verification', [AuthController::class, 'resendVerification']);
    
    // Public events (no auth required)
    Route::get('events/search', [EventController::class, 'search']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);
    
    // Public resource access (some resources may be public)
    Route::get('events/{event}/resources', [ResourceController::class, 'index']);
    Route::get('resources/{resource}', [ResourceController::class, 'show']);
    Route::get('resources/{resource}/download', [ResourceController::class, 'download']);
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    
    // User profile routes
    Route::get('profile', [UserController::class, 'profile']);
    Route::put('profile', [UserController::class, 'updateProfile']);
    Route::post('profile/avatar', [UserController::class, 'updateAvatar']);
    Route::delete('profile/avatar', [UserController::class, 'deleteAvatar']);
    Route::get('profile/stats', [UserController::class, 'stats']);
    
    // Event management routes
    Route::apiResource('events', EventController::class)->except(['index', 'show']);
    Route::post('events/{event}/register', [EventController::class, 'register']);
    Route::delete('events/{event}/unregister', [EventController::class, 'unregister']);
    Route::get('events/{event}/attendees', [EventController::class, 'attendees']);
    Route::get('my-events', [EventController::class, 'myEvents']);
    Route::get('my-registrations', [EventController::class, 'myRegistrations']);
    
    // Resource management routes (protected)
    Route::post('events/{event}/resources', [ResourceController::class, 'store']);
    Route::put('resources/{resource}', [ResourceController::class, 'update']);
    Route::delete('resources/{resource}', [ResourceController::class, 'destroy']);
    
    // Admin moderation routes
    Route::middleware('admin')->group(function () {
        Route::post('admin/events/{event}/ban', [EventController::class, 'banEvent']);
        Route::post('admin/events/{event}/unban', [EventController::class, 'unbanEvent']);
        Route::delete('admin/events/{event}/force-delete', [EventController::class, 'forceDelete']);
    });
});
