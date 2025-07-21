<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Get admin dashboard overview
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
            'recent_admin_actions' => AdminLog::with('admin:id,name')->recent(7)->take(10)->get(),
        ];

        return response()->json([
            'message' => 'Admin dashboard data retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Get all users with filtering and pagination
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
            'data' => $users,
        ]);
    }

    /**
     * Ban a user
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
     * Delete a forum post (moderation)
     */
    public function deletePost(Request $request, ForumPost $post): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $postData = $post->toArray();
        $post->delete();

        // Log the admin action
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'content_delete',
            'target_type' => 'post',
            'target_id' => $post->id,
            'description' => "Deleted forum post: {$post->content}",
            'metadata' => [
                'reason' => $request->input('reason'),
                'post_data' => $postData,
            ],
            'ip_address' => $request->ip(),
            'severity' => 'warning',
        ]);

        return response()->json([
            'message' => 'Post deleted successfully',
        ]);
    }

    /**
     * Get admin logs with filtering
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AdminLog::with('admin:id,name');

        if ($request->filled('admin_id')) {
            $query->byAdmin($request->input('admin_id'));
        }

        if ($request->filled('action')) {
            $query->action($request->input('action'));
        }

        if ($request->filled('target_type')) {
            $query->forTarget($request->input('target_type'), $request->input('target_id'));
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
            'data' => $logs,
        ]);
    }

    /**
     * Create a new admin user
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = User::create([
            'name' => $request->input('name'),
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
            'description' => "Created new admin: {$admin->name}",
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'created_at' => $admin->created_at,
            ],
        ], 201);
    }
}
