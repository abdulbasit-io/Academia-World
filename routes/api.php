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
    
    // Community features - Discussion Forums
    Route::get('events/{event}/forums', [\App\Http\Controllers\Api\V1\ForumController::class, 'index']);
    Route::post('events/{event}/forums', [\App\Http\Controllers\Api\V1\ForumController::class, 'store']);
    Route::get('forums/{forum}', [\App\Http\Controllers\Api\V1\ForumController::class, 'show']);
    Route::put('forums/{forum}', [\App\Http\Controllers\Api\V1\ForumController::class, 'update']);
    Route::delete('forums/{forum}', [\App\Http\Controllers\Api\V1\ForumController::class, 'destroy']);
    
    // Forum Posts
    Route::get('forums/{forum}/posts', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'index']);
    Route::post('forums/{forum}/posts', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'store']);
    Route::get('posts/{post}', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'show']);
    Route::put('posts/{post}', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'update']);
    Route::delete('posts/{post}', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'destroy']);
    Route::post('posts/{post}/like', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'toggleLike']);
    Route::post('posts/{post}/pin', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'togglePin']);
    Route::post('posts/{post}/solution', [\App\Http\Controllers\Api\V1\ForumPostController::class, 'markAsSolution']);
    
    // User Connections/Networking
    Route::get('connections', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'index']);
    Route::get('connections/pending', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'pending']);
    Route::post('connections', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'store']);
    Route::put('connections/{connection}/respond', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'respond']);
    Route::delete('connections/{connection}', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'destroy']);
    Route::get('users/search', [\App\Http\Controllers\Api\V1\UserConnectionController::class, 'searchUsers']);
    
    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Admin Dashboard & Analytics
        Route::get('dashboard', [\App\Http\Controllers\Api\V1\AdminController::class, 'dashboard']);
        Route::get('analytics', [\App\Http\Controllers\Api\V1\AdminController::class, 'analytics']);
        Route::get('platform-health', [\App\Http\Controllers\Api\V1\AdminController::class, 'platformHealth']);
        Route::get('logs', [\App\Http\Controllers\Api\V1\AdminController::class, 'adminLogs']);
        
        // User Management
        Route::get('users', [\App\Http\Controllers\Api\V1\AdminController::class, 'users']);
        Route::post('users/{user}/toggle-ban', [\App\Http\Controllers\Api\V1\AdminController::class, 'toggleUserBan']);
        
        // Event Management
        Route::get('events', [\App\Http\Controllers\Api\V1\AdminController::class, 'events']);
        Route::put('events/{event}/status', [\App\Http\Controllers\Api\V1\AdminController::class, 'updateEventStatus']);
        Route::delete('events/{event}', [\App\Http\Controllers\Api\V1\AdminController::class, 'deleteEvent']);
        
        // Content Moderation
        Route::get('forum-posts', [\App\Http\Controllers\Api\V1\AdminController::class, 'forumPosts']);
        Route::delete('forum-posts/{post}', [\App\Http\Controllers\Api\V1\AdminController::class, 'deleteForumPost']);
    });
});
