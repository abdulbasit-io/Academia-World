<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ResourceController;
use App\Http\Controllers\API\ForumController;
use App\Http\Controllers\API\ForumPostController;
use App\Http\Controllers\API\UserConnectionController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AnalyticsController;

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
    Route::post('auth/login', [AuthController::class, 'login'])->name('login');
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
Route::prefix('v1')->middleware(['cookie.auth', 'auth:sanctum'])->group(function () {
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
    Route::get('events/{event}/forums', [ForumController::class, 'index']);
    Route::post('events/{event}/forums', [ForumController::class, 'store']);
    Route::get('forums/{forum}', [ForumController::class, 'show']);
    Route::put('forums/{forum}', [ForumController::class, 'update']);
    Route::delete('forums/{forum}', [ForumController::class, 'destroy']);
    
    // Forum Posts
    Route::get('forums/{forum}/posts', [ForumPostController::class, 'index']);
    Route::post('forums/{forum}/posts', [ForumPostController::class, 'store']);
    Route::get('posts/{post}', [ForumPostController::class, 'show']);
    Route::put('posts/{post}', [ForumPostController::class, 'update']);
    Route::delete('posts/{post}', [ForumPostController::class, 'destroy']);
    Route::post('posts/{post}/like', [ForumPostController::class, 'toggleLike']);
    Route::post('posts/{post}/pin', [ForumPostController::class, 'togglePin']);
    Route::post('posts/{post}/solution', [ForumPostController::class, 'markAsSolution']);
    
    // User Connections/Networking
    Route::get('connections', [UserConnectionController::class, 'index']);
    Route::get('connections/pending', [UserConnectionController::class, 'pending']);
    Route::post('connections', [UserConnectionController::class, 'store']);
    Route::put('connections/{connection}/respond', [UserConnectionController::class, 'respond']);
    Route::delete('connections/{connection}', [UserConnectionController::class, 'destroy']);
    Route::get('users/search', [UserConnectionController::class, 'searchUsers']);
    
    // Admin routes
    Route::middleware(['cookie.auth', 'auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        // Dashboard & Analytics
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('analytics/user-engagement', [AnalyticsController::class, 'userEngagement']);
        Route::get('analytics/event-engagement', [AnalyticsController::class, 'eventEngagement']);
        Route::get('analytics/forum-activity', [AnalyticsController::class, 'forumActivity']);
        Route::get('platform-health', [AdminController::class, 'platformHealth']);
        Route::get('logs', [AdminController::class, 'logs']);
        
        // User Management
        Route::get('users', [AdminController::class, 'users']);
        Route::post('users', [AdminController::class, 'createAdmin']);
        Route::put('users/{user}/ban', [AdminController::class, 'banUser']);
        Route::post('users/{user}/promote', [AdminController::class, 'promoteUser']);
        Route::post('users/{user}/demote', [AdminController::class, 'demoteUser']);
        
        // Event Management
        Route::get('events', [AdminController::class, 'events']);
        Route::get('events/{event}', [AdminController::class, 'showEvent']);
        Route::put('events/{event}/status', [AdminController::class, 'updateEventStatus']);
        Route::delete('events/{event}', [AdminController::class, 'deleteEvent']);
        
        // Content Moderation
        Route::get('posts', [AdminController::class, 'forumPosts']);
        Route::delete('posts/{post}', [AdminController::class, 'deleteForumPost']);
        
        // Missing Event Management Endpoints
        Route::post('events/{event}/ban', [AdminController::class, 'banEvent']);
        Route::post('events/{event}/unban', [AdminController::class, 'unbanEvent']);
        Route::delete('events/{event}/force-delete', [AdminController::class, 'forceDeleteEvent']);
        
        // Missing Analytics Endpoints
        Route::post('analytics/generate-daily', [AnalyticsController::class, 'generateDaily']);
        
        // Platform Health
        Route::get('platform-health', [AdminController::class, 'platformHealth']);
    });
});
