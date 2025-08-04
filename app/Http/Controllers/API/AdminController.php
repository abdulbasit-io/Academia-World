<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin",
 *     description="Administrative operations for platform management"
 * )
 */

class AdminController extends Controller
{
    /**
     * Get admin dashboard overview
     * 
     * @OA\Get(
     *     path="/api/v1/admin/dashboard",
     *     summary="Get admin dashboard overview",
     *     description="Retrieve comprehensive platform statistics and recent activity for admin dashboard",
     *     operationId="getAdminDashboard",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin dashboard data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_users", type="integer", description="Total number of users"),
     *                 @OA\Property(property="total_events", type="integer", description="Total number of events"),
     *                 @OA\Property(property="total_forums", type="integer", description="Total number of forums"),
     *                 @OA\Property(property="total_posts", type="integer", description="Total number of forum posts"),
     *                 @OA\Property(
     *                     property="recent_users",
     *                     type="array",
     *                     description="5 most recent user registrations",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="recent_events",
     *                     type="array",
     *                     description="5 most recent events",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="title", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="recent_admin_actions",
     *                     type="array",
     *                     description="10 most recent admin actions",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="admin_id", type="integer"),
     *                         @OA\Property(property="action", type="string"),
     *                         @OA\Property(property="target_type", type="string"),
     *                         @OA\Property(property="target_id", type="integer"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="severity", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - Admin privileges required",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_events' => Event::count(),
            'total_forums' => DiscussionForum::count(),
            'total_posts' => ForumPost::count(),
            'recent_users' => User::orderBy('created_at', 'desc')->take(5)->get(['id', 'name', 'email', 'created_at']),
            'recent_events' => Event::orderBy('created_at', 'desc')->take(5)->get(['uuid', 'title', 'created_at']),
            'recent_admin_actions' => AdminLog::with('admin:id,uuid,first_name,last_name')->recent(7)->take(10)->get(),
        ];

        return response()->json([
            'message' => 'Admin dashboard data retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Get all users with filtering and pagination
     * 
     * @OA\Get(
     *     path="/api/v1/admin/users",
     *     summary="Get all users with filtering",
     *     description="Retrieve all platform users with filtering and pagination options for admin management",
     *     operationId="getAdminUsers",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search users by name or email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_admin",
     *         in="query",
     *         description="Filter by admin status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="is_banned",
     *         in="query",
     *         description="Filter by ban status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of users per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name", type="string"),
     *                     @OA\Property(property="full_name", type="string"),
     *                     @OA\Property(property="email", type="string", format="email"),
     *                     @OA\Property(property="institution", type="string"),
     *                     @OA\Property(property="department", type="string"),
     *                     @OA\Property(property="position", type="string"),
     *                     @OA\Property(property="account_status", type="string"),
     *                     @OA\Property(property="is_admin", type="boolean"),
     *                     @OA\Property(property="is_banned", type="boolean"),
     *                     @OA\Property(property="ban_reason", type="string", nullable=true),
     *                     @OA\Property(property="banned_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_admin')) {
            $query->where('is_admin', $request->boolean('is_admin'));
        }

        if ($request->filled('is_banned')) {
            $query->where('is_banned', $request->boolean('is_banned'));
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users->map(function($user) {
                return [
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'institution' => $user->institution,
                    'department' => $user->department,
                    'position' => $user->position,
                    'account_status' => $user->account_status,
                    'is_admin' => $user->is_admin,
                    'is_banned' => $user->is_banned,
                    'ban_reason' => $user->ban_reason,
                    'banned_at' => $user->banned_at,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            }),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Ban a user
     * 
     * @OA\Put(
     *     path="/api/v1/admin/users/{user}/ban",
     *     summary="Ban or unban a user",
     *     description="Ban a user from the platform with a specified reason, or unban if already banned",
     *     operationId="banUser",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", maxLength=500, description="Reason for banning the user", example="Violating community guidelines")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User banned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User banned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot ban admin user",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot ban an admin user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="errors", type="object"))
     *     )
     * )
     */
    public function banUser(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($user->is_admin) {
            return response()->json([
                'message' => 'Cannot ban an admin user'
            ], 403);
        }

        $user->update(['is_banned' => true]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'user_ban',
            'target_type' => 'user',
            'target_id' => $user->id,
            'description' => "Banned user: {$user->name}",
            'changes' => [
                'before' => ['is_banned' => false],
                'after' => ['is_banned' => true],
            ],
            'metadata' => ['reason' => $request->input('reason')],
            'ip_address' => $request->ip(),
            'severity' => 'warning',
        ]);

        return response()->json([
            'message' => 'User banned successfully',
        ]);
    }

    /**
     * Unban a user
     * 
     * @OA\Put(
     *     path="/api/v1/admin/users/{user}/unban",
     *     summary="Unban a user",
     *     description="Remove ban from a previously banned user",
     *     operationId="unbanUser",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User unbanned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User unbanned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function unbanUser(Request $request, User $user): JsonResponse
    {
        $user->update(['is_banned' => false]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'user_unban',
            'target_type' => 'user',
            'target_id' => $user->id,
            'description' => "Unbanned user: {$user->name}",
            'changes' => [
                'before' => ['is_banned' => true],
                'after' => ['is_banned' => false],
            ],
            'ip_address' => $request->ip(),
            'severity' => 'info',
        ]);

        return response()->json([
            'message' => 'User unbanned successfully',
        ]);
    }

    /**
     * Promote user to admin
     * 
     * @OA\Post(
     *     path="/api/v1/admin/users/{user}/promote",
     *     summary="Promote user to admin",
     *     description="Grant admin privileges to a regular user",
     *     operationId="promoteUser",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User promoted to admin successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User promoted to admin successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="User is already an admin",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User is already an admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function promoteUser(Request $request, User $user): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json([
                'message' => 'User is already an admin'
            ], 422);
        }

        $user->update(['is_admin' => true]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'user_promote',
            'target_type' => 'user',
            'target_id' => $user->id,
            'description' => "Promoted user to admin: {$user->name}",
            'changes' => [
                'before' => ['is_admin' => false],
                'after' => ['is_admin' => true],
            ],
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'User promoted to admin successfully',
        ]);
    }

    /**
     * Demote admin to regular user
     * 
     * @OA\Post(
     *     path="/api/v1/admin/users/{user}/demote",
     *     summary="Demote admin to regular user",
     *     description="Remove admin privileges from an admin user",
     *     operationId="demoteUser",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Admin demoted to user successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin demoted to user successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="User is not an admin",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User is not an admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot demote yourself",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot demote yourself")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function demoteUser(Request $request, User $user): JsonResponse
    {
        if (!$user->is_admin) {
            return response()->json([
                'message' => 'User is not an admin'
            ], 422);
        }

        // Prevent self-demotion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot demote yourself'
            ], 403);
        }

        $user->update(['is_admin' => false]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'user_demote',
            'target_type' => 'user',
            'target_id' => $user->id,
            'description' => "Demoted admin to user: {$user->name}",
            'changes' => [
                'before' => ['is_admin' => true],
                'after' => ['is_admin' => false],
            ],
            'ip_address' => $request->ip(),
            'severity' => 'warning',
        ]);

        return response()->json([
            'message' => 'Admin demoted to user successfully',
        ]);
    }

    /**
     * Get admin logs with filtering
     * 
     * @OA\Get(
     *     path="/api/v1/admin/logs",
     *     summary="Get admin activity logs",
     *     description="Retrieve admin activity logs with filtering options for audit trail",
     *     operationId="getAdminLogs",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="admin_uuid",
     *         in="query",
     *         description="Filter by specific admin UUID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filter by action type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"user_ban", "user_unban", "user_promote", "user_demote", "event_ban", "event_unban", "post_delete"})
     *     ),
     *     @OA\Parameter(
     *         name="target_type",
     *         in="query",
     *         description="Filter by target type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"user", "event", "post", "forum_post"})
     *     ),
     *     @OA\Parameter(
     *         name="target_uuid",
     *         in="query",
     *         description="Filter by target ID (use with target_type)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="severity",
     *         in="query",
     *         description="Filter by severity level",
     *         required=false,
     *         @OA\Schema(type="string", enum={"info", "warning", "medium", "high", "critical"})
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Limit to recent days",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=365)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of logs per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Admin logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin logs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid"),
     *                     @OA\Property(property="action", type="string"),
     *                     @OA\Property(property="target_type", type="string"),
     *                     @OA\Property(property="target_uuid", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="changes", type="object"),
     *                     @OA\Property(property="metadata", type="object"),
     *                     @OA\Property(property="ip_address", type="string"),
     *                     @OA\Property(property="severity", type="string"),
     *                     @OA\Property(
     *                         property="admin",
     *                         type="object",
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="first_name", type="string"),
     *                         @OA\Property(property="last_name", type="string"),
     *                         @OA\Property(property="email", type="string")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AdminLog::with('admin:id,uuid,first_name,last_name,email');

        if ($request->filled('admin_uuid')) {
            $query->byAdmin($request->input('admin_uuid'));
        }

        if ($request->filled('action')) {
            $query->action($request->input('action'));
        }

        if ($request->filled('target_type')) {
            $query->forTarget($request->input('target_type'), $request->input('target_uuid'));
        }

        if ($request->filled('severity')) {
            $query->severity($request->input('severity'));
        }

        if ($request->filled('days')) {
            $query->recent($request->input('days'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'message' => 'Admin logs retrieved successfully',
            'data' => $logs->map(function($log) {
                return [
                    'uuid' => $log->uuid,
                    'action' => $log->action,
                    'target_type' => $log->target_type,
                    'target_uuid' => $log->target_uuid,
                    'description' => $log->description,
                    'changes' => $log->changes,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'severity' => $log->severity,
                    'admin' => $log->admin ? [
                        'uuid' => $log->admin->uuid,
                        'first_name' => $log->admin->first_name,
                        'last_name' => $log->admin->last_name,
                        'email' => $log->admin->email,
                    ] : null,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ];
            }),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    /**
     * Create a new admin user
     * 
     * @OA\Post(
     *     path="/api/v1/admin/users",
     *     summary="Create a new admin user",
     *     description="Create a new user account with admin privileges",
     *     operationId="createAdmin",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="first_name", type="string", maxLength=255, description="First name of the admin", example="John"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, description="Last name of the admin", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, description="Email address (must be unique)", example="admin@university.edu"),
     *             @OA\Property(property="password", type="string", minLength=8, description="Password (minimum 8 characters)", example="SecurePass123"),
     *             @OA\Property(property="password_confirmation", type="string", description="Password confirmation", example="SecurePass123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Admin created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="errors", type="object"))
     *     )
     * )
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'name' => $request->input('first_name') . ' ' . $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'admin_create',
            'target_type' => 'user',
            'target_id' => $admin->id,
            'description' => "Created new admin: {$admin->first_name} {$admin->last_name}",
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'data' => [
                'uuid' => $admin->uuid,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
                'created_at' => $admin->created_at,
            ],
        ], 201);
    }

    /**
     * Get all events for admin management
     * 
     * @OA\Get(
     *     path="/api/v1/admin/events",
     *     summary="Get all events for admin management",
     *     description="Retrieve all events with filtering options for administrative oversight",
     *     operationId="getAdminEvents",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by event status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "cancelled", "completed", "banned"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search events by title or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of events per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Events retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object", @OA\Property(property="uuid", type="string"), @OA\Property(property="title", type="string"), @OA\Property(property="status", type="string"))
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function events(Request $request): JsonResponse
    {
        $query = Event::with(['host:id,uuid,first_name,last_name,email,institution'])
                      ->withCount('registrations');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('created_at', 'desc')
                       ->paginate($request->input('per_page', 15));

        return response()->json([
            'message' => 'Events retrieved successfully',
            'data' => $events->items(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get single event details for admin
     * 
     * @OA\Get(
     *     path="/api/v1/admin/events/{event}",
     *     summary="Get event details for admin",
     *     description="Retrieve detailed event information including registrations, resources, and forums for admin oversight",
     *     operationId="getAdminEventDetails",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="location_type", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="meeting_link", type="string"),
     *                 @OA\Property(property="start_date", type="string", format="date-time"),
     *                 @OA\Property(property="end_date", type="string", format="date-time"),
     *                 @OA\Property(property="capacity", type="integer"),
     *                 @OA\Property(property="registration_deadline", type="string", format="date-time"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="host", type="object"),
     *                 @OA\Property(property="registrations", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="registration_count", type="integer"),
     *                 @OA\Property(property="resources_count", type="integer"),
     *                 @OA\Property(property="forums_count", type="integer"),
     *                 @OA\Property(property="available_spots", type="integer"),
     *                 @OA\Property(property="is_full", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function showEvent(Event $event): JsonResponse
    {
        $event->load([
            'host:id,first_name,last_name,email,institution,department,position',
            'registrations' => function($query) {
                $query->with('user:id,first_name,last_name,email,institution');
            },
            'resources:id,event_id,title,file_size,download_count,created_at',
            'forums:id,event_id,title,description,created_at'
        ]);

        return response()->json([
            'message' => 'Event details retrieved successfully',
            'data' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'description' => $event->description,
                'status' => $event->status,
                'location_type' => $event->location_type,
                'location' => $event->location,
                'meeting_link' => $event->meeting_link,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'capacity' => $event->capacity,
                'registration_deadline' => $event->registration_deadline,
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
                'host' => $event->host,
                'registrations' => $event->registrations,
                'registration_count' => $event->registrations->count(),
                'resources_count' => $event->resources->count(),
                'forums_count' => $event->forums->count(),
                'available_spots' => $event->available_spots,
                'is_full' => $event->is_full,
            ],
        ]);
    }

    /**
     * Update event status
     * 
     * @OA\Put(
     *     path="/api/v1/admin/events/{event}/status",
     *     summary="Update event status",
     *     description="Update the status of an event (admin only)",
     *     operationId="updateEventStatus",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"draft", "published", "cancelled", "completed"}, description="New event status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event status updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="errors", type="object"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function updateEventStatus(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,cancelled,completed'],
        ]);

        $oldStatus = $event->status;
        $event->update(['status' => $request->input('status')]);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'event_status_update',
            'target_type' => 'event',
            'target_id' => $event->id,
            'description' => "Changed event status from {$oldStatus} to {$event->status}: {$event->title}",
            'ip_address' => $request->ip(),
            'severity' => 'medium',
        ]);

        return response()->json([
            'message' => 'Event status updated successfully',
            'data' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'status' => $event->status,
                'updated_at' => $event->updated_at,
            ],
        ]);
    }

    /**
     * Delete an event (admin only)
     * 
     * @OA\Delete(
     *     path="/api/v1/admin/events/{event}",
     *     summary="Delete an event",
     *     description="Delete an event and all associated data (soft delete)",
     *     operationId="deleteEvent",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function deleteEvent(Request $request, Event $event): JsonResponse
    {
        $eventTitle = $event->title;
        $eventId = $event->id;

        // Delete associated data
        $event->registrations()->delete();
        $event->resources()->delete();
        $event->forums()->delete();
        $event->delete();

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'event_delete',
            'target_type' => 'event',
            'target_id' => $eventId,
            'description' => "Deleted event: {$eventTitle}",
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    /**
     * Ban an event (admin only)
     * 
     * @OA\Post(
     *     path="/api/v1/admin/events/{event}/ban",
     *     summary="Ban an event",
     *     description="Ban an event making it invisible to non-admin users",
     *     operationId="banEvent",
     *     tags={"Admin"},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", maxLength=500, description="Reason for banning the event")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event banned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event banned successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="status", type="string", example="banned"),
     *                 @OA\Property(property="reason", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function banEvent(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $oldStatus = $event->status;
        $event->update(['status' => 'banned']);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'event_ban',
            'target_type' => 'event',
            'target_id' => $event->id,
            'description' => "Banned event: {$event->title}",
            'metadata' => [
                'reason' => $request->input('reason'),
                'previous_status' => $oldStatus,
            ],
            'ip_address' => $request->ip(),
            'severity' => 'high',
        ]);

        return response()->json([
            'message' => 'Event banned successfully',
            'data' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'status' => $event->status,
                'reason' => $request->input('reason'),
            ],
        ]);
    }

    /**
     * Unban an event (admin only)
     * 
     * @OA\Post(
     *     path="/api/v1/admin/events/{event}/unban",
     *     summary="Unban an event",
     *     description="Restore a banned event to published status",
     *     operationId="unbanEvent",
     *     tags={"Admin"},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event unbanned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event unbanned successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="status", type="string", example="published")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Event is not currently banned",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event is not currently banned")
     *         )
     *     )
     * )
     */
    public function unbanEvent(Request $request, Event $event): JsonResponse
    {
        if ($event->status !== 'banned') {
            return response()->json([
                'message' => 'Event is not currently banned',
            ], 400);
        }

        $event->update(['status' => 'published']);

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'event_unban',
            'target_type' => 'event',
            'target_id' => $event->id,
            'description' => "Unbanned event: {$event->title}",
            'ip_address' => $request->ip(),
            'severity' => 'medium',
        ]);

        return response()->json([
            'message' => 'Event unbanned successfully',
            'data' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'status' => $event->status,
            ],
        ]);
    }

    /**
     * Force delete an event permanently (admin only)
     * 
     * @OA\Delete(
     *     path="/api/v1/admin/events/{event}/force-delete",
     *     summary="Permanently delete an event",
     *     description="Permanently delete an event and all associated data. This action cannot be undone.",
     *     operationId="forceDeleteEvent",
     *     tags={"Admin"},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         description="Event UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"confirmation", "reason"},
     *             @OA\Property(property="confirmation", type="string", enum={"DELETE"}, description="Must be 'DELETE' to confirm"),
     *             @OA\Property(property="reason", type="string", maxLength=500, description="Reason for permanent deletion")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event permanently deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event permanently deleted successfully")
     *         )
     *     )
     * )
     */
    public function forceDeleteEvent(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $eventTitle = $event->title;
        $eventId = $event->id;

        // Delete all associated data permanently
        $event->registrations()->forceDelete();
        $event->resources()->forceDelete();
        $event->forums()->each(function ($forum) {
            $forum->posts()->forceDelete();
            $forum->forceDelete();
        });
        $event->forceDelete();

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'event_force_delete',
            'target_type' => 'event',
            'target_id' => $eventId,
            'description' => "Force deleted event: {$eventTitle}",
            'metadata' => [
                'reason' => $request->input('reason'),
                'confirmation' => 'DELETE',
            ],
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Event permanently deleted successfully',
        ]);
    }

    /**
     * Get all forum posts with filtering and pagination
     * 
     * @OA\Get(
     *     path="/api/v1/admin/posts",
     *     summary="Get all forum posts for moderation",
     *     description="Retrieve forum posts with filtering options for admin moderation",
     *     operationId="getAdminForumPosts",
     *     tags={"Admin"},
     *     @OA\Parameter(
     *         name="forum_id",
     *         in="query",
     *         description="Filter by specific forum ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by specific user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="reported",
     *         in="query",
     *         description="Show only reported posts",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="date_range",
     *         in="query",
     *         description="Filter by date range",
     *         required=false,
     *         @OA\Schema(type="string", enum={"today", "week", "month"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of posts per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum posts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum posts retrieved successfully"),
     *             @OA\Property(property="data", type="object", description="Paginated forum posts")
     *         )
     *     )
     * )
     */
    public function forumPosts(Request $request): JsonResponse
    {
        $query = ForumPost::with(['user:id,uuid,first_name,last_name,email', 'forum:id,title,event_id', 'forum.event:id,uuid,title']);

        if ($request->filled('forum_id')) {
            $query->where('forum_id', $request->input('forum_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('reported') && $request->boolean('reported')) {
            $query->where('is_reported', true);
        }

        if ($request->filled('date_range')) {
            $dateRange = $request->input('date_range');
            if ($dateRange === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($dateRange === 'week') {
                $query->where('created_at', '>=', now()->subWeek());
            } elseif ($dateRange === 'month') {
                $query->where('created_at', '>=', now()->subMonth());
            }
        }

        $perPage = $request->input('per_page', 15);
        $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Forum posts retrieved successfully',
            'data' => $posts,
        ]);
    }

    /**
     * Delete a forum post (admin only)
     * 
     * @OA\Delete(
     *     path="/api/v1/admin/posts/{post}",
     *     summary="Delete a forum post (admin moderation)",
     *     description="Delete a forum post as part of content moderation with reason logging",
     *     operationId="deleteForumPost",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="post",
     *         in="path",
     *         description="Forum Post ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", maxLength=500, description="Reason for deleting the forum post", example="Inappropriate content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum post deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum post deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Forum post not found",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="error", type="string"))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"), @OA\Property(property="errors", type="object"))
     *     )
     * )
     */
    public function deleteForumPost(Request $request, ForumPost $post): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $postContent = substr($post->content, 0, 100) . '...';
        $postId = $post->id;

        $post->delete();

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'post_delete',
            'target_type' => 'forum_post',
            'target_id' => $postId,
            'description' => "Deleted forum post: {$postContent}",
            'metadata' => [
                'reason' => $request->input('reason'),
                'forum_id' => $post->forum_id,
            ],
            'ip_address' => $request->ip(),
            'severity' => 'medium',
        ]);

        return response()->json([
            'message' => 'Forum post deleted successfully',
        ]);
    }

    /**
     * Get platform health metrics
     * 
     * @OA\Get(
     *     path="/api/v1/admin/platform-health",
     *     summary="Get platform health metrics",
     *     description="Retrieve comprehensive platform health and system metrics",
     *     operationId="getPlatformHealth",
     *     tags={"Admin"},
     *     @OA\Response(
     *         response=200,
     *         description="Platform health retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Platform health retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="database",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="connections", type="string", example="active"),
     *                     @OA\Property(property="last_backup", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(property="active_users_24h", type="integer"),
     *                 @OA\Property(
     *                     property="system_resources",
     *                     type="object",
     *                     @OA\Property(property="memory_usage", type="integer"),
     *                     @OA\Property(property="peak_memory", type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="event_statistics",
     *                     type="object",
     *                     @OA\Property(property="total_events", type="integer"),
     *                     @OA\Property(property="active_events", type="integer"),
     *                     @OA\Property(property="upcoming_events", type="integer")
     *                 ),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function platformHealth(): JsonResponse
    {
        $health = [
            'database' => [
                'status' => 'healthy',
                'connections' => DB::connection()->getPdo() ? 'active' : 'inactive',
                'last_backup' => null, // Would need to implement backup tracking
            ],
            'active_users_24h' => User::where('updated_at', '>=', now()->subDay())->count(),
            'system_resources' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ],
            'event_statistics' => [
                'total_events' => Event::count(),
                'active_events' => Event::where('status', 'published')->count(),
                'upcoming_events' => Event::where('status', 'published')
                    ->where('start_date', '>', now())
                    ->count(),
            ],
            'user_metrics' => [
                'total_users' => User::count(),
                'banned_users' => User::where('is_banned', true)->count(),
                'pending_users' => User::whereNull('email_verified_at')->count(),
            ],
            'queue_status' => [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        return response()->json([
            'message' => 'Platform health retrieved successfully',
            'data' => $health,
        ]);
    }
}
