<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\PlatformMetric;
use App\Models\User;
use App\Models\Event;
use App\Models\ForumPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Track user engagement action
     */
    public function trackEngagement(string $actionType, array $data = []): void
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return; // Skip tracking for unauthenticated users
            }

            AnalyticsEvent::create([
                'user_id' => $userId,
                'action' => $actionType,
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'occurred_at' => now(),
            ]);

            Log::info('Analytics tracked', [
                'user_id' => $userId,
                'action' => $actionType,
                'entity_type' => $data['entity_type'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track analytics', [
                'error' => $e->getMessage(),
                'action' => $actionType,
                'user_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Generate platform analytics metrics
     */
    public function generatePlatformMetrics(): array
    {
        try {
            $metrics = [
                'users' => $this->generateUserEngagementMetrics(),
                'events' => $this->generateEventActivityMetrics(),
                'forum' => $this->generateForumActivityMetrics(),
                'platform' => $this->generatePlatformOverviewMetrics(),
            ];

            // Store generated metrics
            $this->storePlatformMetrics($metrics);

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to generate platform metrics', ['error' => $e->getMessage()]);
            return $this->getFallbackMetrics();
        }
    }

    /**
     * Generate user engagement metrics
     */
    public function generateUserEngagementMetrics(): array
    {
        try {
            // Try from analytics events first
            $activeUsers = AnalyticsEvent::where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->distinct('user_id')
                ->count('user_id');

            $dailyActiveUsers = AnalyticsEvent::where('occurred_at', '>=', Carbon::now()->subDay())
                ->distinct('user_id')
                ->count('user_id');

            $weeklyActiveUsers = AnalyticsEvent::where('occurred_at', '>=', Carbon::now()->subWeek())
                ->distinct('user_id')
                ->count('user_id');

            // Fallback to direct database queries if analytics data is insufficient
            if ($activeUsers === 0) {
                $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subDays(30))->count();
                $dailyActiveUsers = User::where('last_login_at', '>=', Carbon::now()->subDay())->count();
                $weeklyActiveUsers = User::where('last_login_at', '>=', Carbon::now()->subWeek())->count();
            }

            return [
                'total_users' => User::count(),
                'active_users_30d' => $activeUsers,
                'daily_active_users' => $dailyActiveUsers,
                'weekly_active_users' => $weeklyActiveUsers,
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'new_users_this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate user engagement metrics', ['error' => $e->getMessage()]);
            return [
                'total_users' => User::count(),
                'active_users_30d' => 0,
                'daily_active_users' => 0,
                'weekly_active_users' => 0,
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'new_users_this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            ];
        }
    }

    /**
     * Generate event activity metrics
     */
    public function generateEventActivityMetrics(): array
    {
        try {
            // Try from analytics events first
            $eventViews = AnalyticsEvent::where('action_type', 'event_view')
                ->where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $eventRegistrations = AnalyticsEvent::where('action_type', 'event_registration')
                ->where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->count();

            // Fallback to direct database queries
            if ($eventViews === 0) {
                $eventViews = 0; // No direct way to track views without analytics
            }

            if ($eventRegistrations === 0) {
                $eventRegistrations = DB::table('event_user_registrations')
                    ->where('created_at', '>=', Carbon::now()->subDays(30))
                    ->count();
            }

            return [
                'total_events' => Event::count(),
                'published_events' => Event::where('status', 'published')->count(),
                'events_this_month' => Event::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'event_views_30d' => $eventViews,
                'event_registrations_30d' => $eventRegistrations,
                'average_registrations_per_event' => $this->getAverageRegistrationsPerEvent(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate event activity metrics', ['error' => $e->getMessage()]);
            return [
                'total_events' => Event::count(),
                'published_events' => Event::where('status', 'published')->count(),
                'events_this_month' => Event::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'event_views_30d' => 0,
                'event_registrations_30d' => 0,
                'average_registrations_per_event' => 0,
            ];
        }
    }

    /**
     * Generate forum activity metrics
     */
    public function generateForumActivityMetrics(): array
    {
        try {
            // Try from analytics events first
            $forumPosts = AnalyticsEvent::where('action_type', 'post_creation')
                ->where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $forumLikes = AnalyticsEvent::where('action_type', 'post_like')
                ->where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->count();

            // Fallback to direct database queries
            if ($forumPosts === 0) {
                $forumPosts = ForumPost::where('created_at', '>=', Carbon::now()->subDays(30))->count();
            }

            if ($forumLikes === 0) {
                $forumLikes = DB::table('forum_post_likes')
                    ->where('created_at', '>=', Carbon::now()->subDays(30))
                    ->count();
            }

            return [
                'total_posts' => ForumPost::count(),
                'posts_this_month' => ForumPost::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'new_posts_30d' => $forumPosts,
                'post_likes_30d' => $forumLikes,
                'active_discussions' => ForumPost::whereNull('parent_id')
                    ->where('updated_at', '>=', Carbon::now()->subWeek())
                    ->count(),
                'total_discussions' => ForumPost::whereNull('parent_id')->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate forum activity metrics', ['error' => $e->getMessage()]);
            return [
                'total_posts' => ForumPost::count(),
                'posts_this_month' => ForumPost::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'new_posts_30d' => 0,
                'post_likes_30d' => 0,
                'active_discussions' => 0,
                'total_discussions' => ForumPost::whereNull('parent_id')->count(),
            ];
        }
    }

    /**
     * Generate platform overview metrics
     */
    public function generatePlatformOverviewMetrics(): array
    {
        try {
            $totalActions = AnalyticsEvent::where('occurred_at', '>=', Carbon::now()->subDays(30))->count();
            
            $topActions = AnalyticsEvent::select('action', DB::raw('count(*) as count'))
                ->where('occurred_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->pluck('count', 'action')
                ->toArray();

            return [
                'total_actions_30d' => $totalActions,
                'top_actions' => $topActions,
                'platform_health' => $this->calculatePlatformHealth(),
                'growth_rate' => $this->calculateGrowthRate(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate platform overview metrics', ['error' => $e->getMessage()]);
            return [
                'total_actions_30d' => 0,
                'top_actions' => [],
                'platform_health' => 'unknown',
                'growth_rate' => 0,
            ];
        }
    }

    /**
     * Store platform metrics
     */
    private function storePlatformMetrics(array $metrics): void
    {
        try {
            $today = Carbon::now()->toDateString();
            
            // Store individual metric types for easier querying
            PlatformMetric::updateOrCreate(
                ['metric_type' => 'user_engagement', 'metric_key' => 'daily_stats', 'metric_date' => $today],
                ['value' => $metrics['users']]
            );
            
            PlatformMetric::updateOrCreate(
                ['metric_type' => 'event_activity', 'metric_key' => 'daily_stats', 'metric_date' => $today],
                ['value' => $metrics['events']]
            );
            
            PlatformMetric::updateOrCreate(
                ['metric_type' => 'forum_activity', 'metric_key' => 'daily_stats', 'metric_date' => $today],
                ['value' => $metrics['forum']]
            );
            
            PlatformMetric::updateOrCreate(
                ['metric_type' => 'platform_overview', 'metric_key' => 'daily_stats', 'metric_date' => $today],
                ['value' => $metrics['platform']]
            );
            
            // Also store complete summary
            PlatformMetric::updateOrCreate(
                ['metric_type' => 'daily_summary', 'metric_key' => 'complete_metrics', 'metric_date' => $today],
                ['value' => $metrics]
            );
        } catch (\Exception $e) {
            Log::error('Failed to store platform metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get fallback metrics when generation fails
     */
    private function getFallbackMetrics(): array
    {
        return [
            'users' => [
                'total_users' => User::count(),
                'active_users_30d' => 0,
                'daily_active_users' => 0,
                'weekly_active_users' => 0,
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'new_users_this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            ],
            'events' => [
                'total_events' => Event::count(),
                'published_events' => Event::where('status', 'published')->count(),
                'events_this_month' => Event::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'event_views_30d' => 0,
                'event_registrations_30d' => 0,
                'average_registrations_per_event' => 0,
            ],
            'forum' => [
                'total_posts' => ForumPost::count(),
                'posts_this_month' => ForumPost::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'new_posts_30d' => 0,
                'post_likes_30d' => 0,
                'active_discussions' => 0,
                'total_discussions' => ForumPost::whereNull('parent_id')->count(),
            ],
            'platform' => [
                'total_actions_30d' => 0,
                'top_actions' => [],
                'platform_health' => 'unknown',
                'growth_rate' => 0,
            ],
        ];
    }

    /**
     * Calculate average registrations per event
     */
    private function getAverageRegistrationsPerEvent(): float
    {
        try {
            $eventCount = Event::count();
            if ($eventCount === 0) return 0;

            $totalRegistrations = DB::table('event_user_registrations')->count();
            return round($totalRegistrations / $eventCount, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate platform health score
     */
    private function calculatePlatformHealth(): string
    {
        try {
            $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subWeek())->count();
            $totalUsers = User::count();
            
            if ($totalUsers === 0) return 'unknown';
            
            $healthScore = ($activeUsers / $totalUsers) * 100;
            
            if ($healthScore >= 70) return 'excellent';
            if ($healthScore >= 50) return 'good';
            if ($healthScore >= 30) return 'fair';
            return 'needs_attention';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Get platform overview for a specific number of days
     */
    public function getPlatformOverview(int $days = 30): array
    {
        try {
            return [
                'period_days' => $days,
                'users' => $this->generateUserEngagementMetrics(),
                'events' => $this->generateEventActivityMetrics(),
                'forum' => $this->generateForumActivityMetrics(),
                'platform' => $this->generatePlatformOverviewMetrics(),
                'generated_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get platform overview', ['error' => $e->getMessage()]);
            return $this->getFallbackMetrics();
        }
    }

    /**
     * Generate daily metrics for a specific date
     */
    public function generateDailyMetrics(Carbon $date): array
    {
        try {
            $metrics = [
                'user_engagement' => $this->generateUserEngagementMetrics(),
                'event_activity' => $this->generateEventActivityMetrics(),
                'forum_activity' => $this->generateForumActivityMetrics(),
                'platform_overview' => $this->generatePlatformOverviewMetrics(),
            ];

            // Store each metric type separately for the date
            foreach ($metrics as $type => $data) {
                PlatformMetric::updateOrCreate(
                    [
                        'metric_type' => $type,
                        'metric_key' => 'daily_stats',
                        'metric_date' => $date->toDateString(),
                    ],
                    [
                        'value' => $data,
                        'updated_at' => now(),
                    ]
                );
            }

            Log::info('Daily metrics generated', [
                'date' => $date->toDateString(),
                'metrics_count' => count($metrics),
            ]);

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to generate daily metrics', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
            return $this->getFallbackMetrics();
        }
    }

    /**
     * Calculate growth rate
     */
    private function calculateGrowthRate(): float
    {
        try {
            $thisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
            $lastMonth = User::where('created_at', '>=', Carbon::now()->subMonth()->startOfMonth())
                ->where('created_at', '<', Carbon::now()->startOfMonth())
                ->count();
            
            if ($lastMonth === 0) return $thisMonth > 0 ? 100 : 0;
            
            return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
