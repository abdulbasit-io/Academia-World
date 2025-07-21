<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Models\AdminLog;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Get admin dashboard overview
     */
    public function dashboard(): JsonResponse
    {
        $overview = $this->analyticsService->getPlatformOverview(30);
        $realtimeMetrics = $this->analyticsService->getRealtimeMetrics();
        
        return response()->json([
            'message' => 'Admin dashboard retrieved successfully',
            'data' => [
                'overview' => $overview,
                'realtime' => $realtimeMetrics,
                'recent_admin_actions' => AdminLog::with('admin:id,name')
                    ->recent(7)
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get(),
            ]
        ]);
    }

    /**
     * Get analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        
        $userMetrics = $this->analyticsService->getUserEngagementMetrics($days);
        $eventMetrics = $this->analyticsService->getEventAnalytics($days);
        $platformStats = $this->analyticsService->getPlatformStatistics();
        
        return response()->json([
            'message' => 'Analytics data retrieved successfully',
            'data' => [
                'user_engagement' => $userMetrics,
                'event_analytics' => $eventMetrics,
                'platform_statistics' => $platformStats,
                'period_days' => $days,
            ]
        ]);
    }

    /**
     * Get all users with filters and pagination
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->string('search');
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

        $users = $query->withCount(['events'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    /**
     * Ban/unban a user
     */
    public function toggleUserBan(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $wasBanned = $user->is_banned;
        $user->update([
            'is_banned' => !$wasBanned,
            'ban_reason' => !$wasBanned ? $request->string('reason') : null,
            'banned_at' => !$wasBanned ? now() : null,
        ]);

        // Log admin action
        AdminLog::create([
            'admin_id' => $request->user()->getKey(),
            'action' => $wasBanned ? 'user_unban' : 'user_ban',
            'target_type' => 'user',
            'target_id' => $user->getKey(),
            'description' => $wasBanned 
                ? "User {$user->name} was unbanned"
                : "User {$user->name} was banned. Reason: {$request->string('reason')}",
            'changes' => [
                'before' => ['is_banned' => $wasBanned],
                'after' => ['is_banned' => !$wasBanned],
            ],
            'metadata' => ['reason' => $request->string('reason')],
            'ip_address' => $request->ip(),
            'severity' => $wasBanned ? 'info' : 'warning',
        ]);

        return response()->json([
            'message' => $wasBanned ? 'User unbanned successfully' : 'User banned successfully',
            'data' => [
                'user_id' => $user->uuid,
                'is_banned' => !$wasBanned,
                'action' => $wasBanned ? 'unbanned' : 'banned',
            ]
        ]);
    }

    /**
     * Get all events with moderation actions
     */
    public function events(Request $request): JsonResponse
    {
        $query = Event::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $events = $query->with(['host:uuid,name,email'])
            ->withCount(['registrations'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Events retrieved successfully',
            'data' => $events,
        ]);
    }

    /**
     * Update event status
     */
    public function updateEventStatus(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:published,cancelled,suspended',
            'reason' => 'required_if:status,cancelled,suspended|string|max:500',
        ]);

        $oldStatus = $event->status;
        $newStatus = $request->string('status');

        $event->update(['status' => $newStatus]);

        // Log admin action
        AdminLog::create([
            'admin_id' => $request->user()->getKey(),
            'action' => 'event_status_change',
            'target_type' => 'event',
            'target_id' => $event->getKey(),
            'description' => "Event '{$event->title}' status changed from {$oldStatus} to {$newStatus}",
            'changes' => [
                'before' => ['status' => $oldStatus],
                'after' => ['status' => $newStatus],
            ],
            'metadata' => [
                'reason' => $request->string('reason'),
                'event_title' => $event->title,
            ],
            'ip_address' => $request->ip(),
            'severity' => $newStatus === 'cancelled' ? 'warning' : 'info',
        ]);

        return response()->json([
            'message' => 'Event status updated successfully',
            'data' => [
                'event_id' => $event->uuid,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        ]);
    }

    /**
     * Delete an event
     */
    public function deleteEvent(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $eventTitle = $event->title;
        $eventUuid = $event->uuid;

        // Log admin action before deletion
        AdminLog::create([
            'admin_id' => $request->user()->getKey(),
            'action' => 'event_delete',
            'target_type' => 'event',
            'target_id' => $event->getKey(),
            'description' => "Event '{$eventTitle}' was deleted",
            'metadata' => [
                'reason' => $request->string('reason'),
                'event_title' => $eventTitle,
                'event_uuid' => $eventUuid,
            ],
            'ip_address' => $request->ip(),
            'severity' => 'critical',
        ]);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
            'data' => [
                'event_id' => $eventUuid,
                'action' => 'deleted',
            ]
        ]);
    }

    /**
     * Get all forum posts with moderation tools
     */
    public function forumPosts(Request $request): JsonResponse
    {
        $query = ForumPost::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('forum_id')) {
            $query->where('forum_id', $request->integer('forum_id'));
        }

        $posts = $query->with([
                'author:uuid,name,email',
                'forum:id,title'
            ])
            ->withCount(['likes', 'replies'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Forum posts retrieved successfully',
            'data' => $posts,
        ]);
    }

    /**
     * Delete a forum post
     */
    public function deleteForumPost(Request $request, ForumPost $post): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $postTitle = $post->title ?? 'Untitled Post';
        $postId = $post->getKey();

        // Log admin action before deletion
        AdminLog::create([
            'admin_id' => $request->user()->getKey(),
            'action' => 'post_delete',
            'target_type' => 'forum_post',
            'target_id' => $post->getKey(),
            'description' => "Forum post '{$postTitle}' was deleted",
            'metadata' => [
                'reason' => $request->string('reason'),
                'post_title' => $postTitle,
                'post_id' => $postId,
                'forum_id' => $post->forum_id,
            ],
            'ip_address' => $request->ip(),
            'severity' => 'warning',
        ]);

        $post->delete();

        return response()->json([
            'message' => 'Forum post deleted successfully',
            'data' => [
                'post_id' => $postId,
                'action' => 'deleted',
            ]
        ]);
    }

    /**
     * Get platform health metrics
     */
    public function platformHealth(): JsonResponse
    {
        $healthData = [
            'database_status' => 'healthy',
            'active_users_last_24h' => $this->analyticsService->generateDailyActiveUsers(now()->subDay())->value['count'] ?? 0,
            'total_events' => Event::count(),
            'active_events' => Event::where('status', 'published')->count(),
            'total_forum_posts' => ForumPost::count(),
            'total_users' => User::count(),
            'banned_users' => User::where('is_banned', true)->count(),
            'pending_users' => User::where('account_status', 'pending')->count(),
        ];

        // Add storage usage if available
        try {
            $storageUsed = disk_total_space(storage_path()) - disk_free_space(storage_path());
            $healthData['storage_used_gb'] = round($storageUsed / 1024 / 1024 / 1024, 2);
        } catch (\Exception $e) {
            $healthData['storage_used_gb'] = 'unavailable';
        }

        return response()->json([
            'message' => 'Platform health metrics retrieved successfully',
            'data' => $healthData,
        ]);
    }

    /**
     * Get admin activity logs
     */
    public function adminLogs(Request $request): JsonResponse
    {
        $query = AdminLog::with('admin:uuid,name,email');

        // Apply filters
        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->string('admin_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'message' => 'Admin logs retrieved successfully',
            'data' => $logs,
        ]);
    }
}
