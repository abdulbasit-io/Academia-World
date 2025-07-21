<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\PlatformMetric;
use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Track a user action
     */
    public function trackUserAction(string $action, array $data = []): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'event_type' => 'user_action',
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Track an engagement metric
     */
    public function trackEngagement(string $action, array $data = []): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'event_type' => 'engagement_metric',
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Generate daily active users metric
     */
    public function generateDailyActiveUsers(Carbon $date): PlatformMetric
    {
        $activeUsers = AnalyticsEvent::where('occurred_at', '>=', $date->startOfDay())
            ->where('occurred_at', '<=', $date->endOfDay())
            ->distinct('user_id')
            ->count('user_id');

        return PlatformMetric::updateOrCreate([
            'metric_type' => 'user_engagement',
            'metric_key' => 'daily_active_users',
            'metric_date' => $date->toDateString(),
            'period' => 'daily',
        ], [
            'value' => ['count' => $activeUsers],
            'breakdown' => $this->getUserEngagementBreakdown($date),
        ]);
    }

    /**
     * Generate event engagement metrics
     */
    public function generateEventEngagement(Carbon $date): PlatformMetric
    {
        $eventMetrics = DB::table('analytics_events')
            ->where('event_type', 'engagement_metric')
            ->where('entity_type', 'event')
            ->whereDate('occurred_at', $date)
            ->select('entity_id', DB::raw('count(*) as engagement_count'))
            ->groupBy('entity_id')
            ->get()
            ->keyBy('entity_id')
            ->map(fn($item) => $item->engagement_count)
            ->toArray();

        $totalEngagement = array_sum($eventMetrics);

        return PlatformMetric::updateOrCreate([
            'metric_type' => 'event_engagement',
            'metric_key' => 'total_daily_engagement',
            'metric_date' => $date->toDateString(),
            'period' => 'daily',
        ], [
            'value' => ['total' => $totalEngagement],
            'breakdown' => [
                'by_event' => $eventMetrics,
                'top_events' => $this->getTopEvents($eventMetrics),
            ],
        ]);
    }

    /**
     * Generate forum activity metrics
     */
    public function generateForumActivity(Carbon $date): PlatformMetric
    {
        $postsCreated = ForumPost::whereDate('created_at', $date)->count();
        $likesGiven = AnalyticsEvent::where('action', 'post_like')
            ->whereDate('occurred_at', $date)
            ->count();

        $forumBreakdown = DB::table('forum_posts')
            ->join('discussion_forums', 'forum_posts.forum_id', '=', 'discussion_forums.id')
            ->whereDate('forum_posts.created_at', $date)
            ->select('discussion_forums.title', DB::raw('count(*) as post_count'))
            ->groupBy('discussion_forums.id', 'discussion_forums.title')
            ->get()
            ->keyBy('title')
            ->map(fn($item) => $item->post_count)
            ->toArray();

        return PlatformMetric::updateOrCreate([
            'metric_type' => 'forum_activity',
            'metric_key' => 'daily_activity',
            'metric_date' => $date->toDateString(),
            'period' => 'daily',
        ], [
            'value' => [
                'posts_created' => $postsCreated,
                'likes_given' => $likesGiven,
            ],
            'breakdown' => [
                'by_forum' => $forumBreakdown,
                'most_active_forums' => $this->getMostActiveForums($forumBreakdown),
            ],
        ]);
    }

    /**
     * Get platform overview statistics
     */
    public function getPlatformOverview(int $days = 30): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($days);

        return [
            'total_users' => User::count(),
            'active_users_period' => $this->getActiveUsersInPeriod($startDate, $endDate),
            'total_events' => Event::count(),
            'events_this_period' => Event::where('created_at', '>=', $startDate)->count(),
            'total_forum_posts' => ForumPost::count(),
            'posts_this_period' => ForumPost::where('created_at', '>=', $startDate)->count(),
            'total_forums' => DiscussionForum::count(),
            'engagement_trend' => $this->getEngagementTrend($startDate, $endDate),
        ];
    }

    /**
     * Get user engagement breakdown
     */
    private function getUserEngagementBreakdown(Carbon $date): array
    {
        $actions = AnalyticsEvent::whereDate('occurred_at', $date)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'actions' => $actions,
            'total_actions' => array_sum($actions),
        ];
    }

    /**
     * Get top events by engagement
     */
    private function getTopEvents(array $eventMetrics): array
    {
        arsort($eventMetrics);
        return array_slice($eventMetrics, 0, 5, true);
    }

    /**
     * Get most active forums
     */
    private function getMostActiveForums(array $forumBreakdown): array
    {
        arsort($forumBreakdown);
        return array_slice($forumBreakdown, 0, 5, true);
    }

    /**
     * Get active users in a specific period
     */
    private function getActiveUsersInPeriod(Carbon $startDate, Carbon $endDate): int
    {
        return AnalyticsEvent::whereBetween('occurred_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Get engagement trend over a period
     */
    private function getEngagementTrend(Carbon $startDate, Carbon $endDate): array
    {
        return AnalyticsEvent::whereBetween('occurred_at', [$startDate, $endDate])
            ->selectRaw('DATE(occurred_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
    }

    /**
     * Get user engagement metrics for a specific period
     */
    public function getUserEngagementMetrics(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        return [
            'total_users' => User::count(),
            'active_users' => $this->getActiveUsersInPeriod($startDate, $endDate),
            'new_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'avg_session_duration' => $this->getAverageSessionDuration($startDate, $endDate),
            'engagement_trend' => $this->getEngagementTrend($startDate, $endDate),
            'user_growth' => $this->getUserGrowthMetrics($days),
        ];
    }

    /**
     * Get event analytics for a specific period
     */
    public function getEventAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        return [
            'total_events' => Event::count(),
            'events_created' => Event::whereBetween('created_at', [$startDate, $endDate])->count(),
            'event_views' => AnalyticsEvent::where('action', 'event_view')
                ->whereBetween('occurred_at', [$startDate, $endDate])->count(),
            'event_registrations' => AnalyticsEvent::where('action', 'event_register')
                ->whereBetween('occurred_at', [$startDate, $endDate])->count(),
            'popular_events' => $this->getPopularEvents($days),
            'event_engagement' => $this->getEventEngagementMetrics($startDate, $endDate),
        ];
    }

    /**
     * Get platform statistics
     */
    public function getPlatformStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'total_events' => Event::count(),
            'total_forums' => DiscussionForum::count(),
            'total_posts' => ForumPost::count(),
            'growth_metrics' => $this->getGrowthMetrics(),
            'platform_health' => $this->getPlatformHealthMetrics(),
        ];
    }

    /**
     * Get real-time metrics
     */
    public function getRealtimeMetrics(): array
    {
        $lastHour = now()->subHour();

        return [
            'active_sessions' => AnalyticsEvent::where('occurred_at', '>=', $lastHour)
                ->distinct('session_id')->count('session_id'),
            'recent_activity' => AnalyticsEvent::where('occurred_at', '>=', $lastHour)
                ->orderBy('occurred_at', 'desc')->take(20)->get(),
            'live_events' => Event::where('start_date', '<=', now())
                ->where('end_date', '>=', now())->count(),
            'current_online_users' => $this->getCurrentOnlineUsers(),
        ];
    }

    /**
     * Update platform metrics (to be run daily)
     */
    public function updatePlatformMetrics(): void
    {
        $today = now()->format('Y-m-d');

        $metrics = [
            'total_users' => User::count(),
            'total_events' => Event::count(),
            'total_forums' => DiscussionForum::count(),
            'total_posts' => ForumPost::count(),
            'daily_active_users' => $this->getActiveUsersInPeriod(now()->startOfDay(), now()->endOfDay()),
            'new_users_today' => User::whereDate('created_at', $today)->count(),
            'events_created_today' => Event::whereDate('created_at', $today)->count(),
        ];

        foreach ($metrics as $name => $value) {
            PlatformMetric::updateOrCreate(
                ['metric_name' => $name, 'metric_date' => $today],
                ['metric_value' => $value]
            );
        }
    }

    /**
     * Get current online users (active in last 15 minutes)
     */
    private function getCurrentOnlineUsers(): int
    {
        return AnalyticsEvent::where('occurred_at', '>=', now()->subMinutes(15))
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Get growth metrics
     */
    private function getGrowthMetrics(): array
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'user_growth_this_month' => User::where('created_at', '>=', $thisMonth)->count(),
            'user_growth_last_month' => User::whereBetween('created_at', [$lastMonth, $thisMonth])->count(),
            'event_growth_this_month' => Event::where('created_at', '>=', $thisMonth)->count(),
            'event_growth_last_month' => Event::whereBetween('created_at', [$lastMonth, $thisMonth])->count(),
        ];
    }

    /**
     * Get platform health metrics
     */
    private function getPlatformHealthMetrics(): array
    {
        return [
            'avg_events_per_user' => User::count() > 0 ? round(Event::count() / User::count(), 2) : 0,
            'avg_posts_per_forum' => DiscussionForum::count() > 0 ? round(ForumPost::count() / DiscussionForum::count(), 2) : 0,
            'user_retention_rate' => $this->calculateUserRetentionRate(),
        ];
    }

    /**
     * Calculate user retention rate
     */
    private function calculateUserRetentionRate(): float
    {
        $totalUsers = User::count();
        if ($totalUsers === 0) return 0;

        $activeUsers = $this->getActiveUsersInPeriod(now()->subDays(30), now());
        return round(($activeUsers / $totalUsers) * 100, 2);
    }

    /**
     * Get average session duration
     */
    private function getAverageSessionDuration(Carbon $startDate, Carbon $endDate): float
    {
        // Simplified calculation - in real implementation, track session start/end times
        $sessions = AnalyticsEvent::whereBetween('occurred_at', [$startDate, $endDate])
            ->groupBy('session_id')
            ->selectRaw('session_id, MIN(occurred_at) as start_time, MAX(occurred_at) as end_time')
            ->get();

        if ($sessions->isEmpty()) return 0;

        $totalDuration = 0;
        foreach ($sessions as $session) {
            $duration = strtotime($session->end_time) - strtotime($session->start_time);
            $totalDuration += $duration;
        }

        return round($totalDuration / $sessions->count() / 60, 2); // Return in minutes
    }

    /**
     * Get user growth metrics
     */
    private function getUserGrowthMetrics(int $days): array
    {
        $startDate = now()->subDays($days);
        $previousPeriodStart = now()->subDays($days * 2);

        $currentPeriod = User::whereBetween('created_at', [$startDate, now()])->count();
        $previousPeriod = User::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();

        $growthRate = $previousPeriod > 0 ? round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2) : 0;

        return [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'growth_rate' => $growthRate,
        ];
    }

    /**
     * Get popular events
     */
    private function getPopularEvents(int $days): array
    {
        $startDate = now()->subDays($days);

        return AnalyticsEvent::where('action', 'event_view')
            ->whereBetween('occurred_at', [$startDate, now()])
            ->selectRaw('entity_id, count(*) as views')
            ->whereNotNull('entity_id')
            ->groupBy('entity_id')
            ->orderBy('views', 'desc')
            ->take(10)
            ->get()
            ->map(function ($item) {
                $event = Event::find($item->entity_id);
                return [
                    'event_id' => $item->entity_id,
                    'event_title' => $event ? $event->title : 'Unknown Event',
                    'views' => $item->views,
                ];
            })
            ->toArray();
    }

    /**
     * Get event engagement metrics
     */
    private function getEventEngagementMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_views' => AnalyticsEvent::where('action', 'event_view')
                ->whereBetween('occurred_at', [$startDate, $endDate])->count(),
            'total_registrations' => AnalyticsEvent::where('action', 'event_register')
                ->whereBetween('occurred_at', [$startDate, $endDate])->count(),
            'total_shares' => AnalyticsEvent::where('action', 'event_share')
                ->whereBetween('occurred_at', [$startDate, $endDate])->count(),
            'conversion_rate' => $this->calculateEventConversionRate($startDate, $endDate),
        ];
    }

    /**
     * Calculate event conversion rate (registrations / views)
     */
    private function calculateEventConversionRate(Carbon $startDate, Carbon $endDate): float
    {
        $views = AnalyticsEvent::where('action', 'event_view')
            ->whereBetween('occurred_at', [$startDate, $endDate])->count();
        
        $registrations = AnalyticsEvent::where('action', 'event_register')
            ->whereBetween('occurred_at', [$startDate, $endDate])->count();

        return $views > 0 ? round(($registrations / $views) * 100, 2) : 0;
    }
}
