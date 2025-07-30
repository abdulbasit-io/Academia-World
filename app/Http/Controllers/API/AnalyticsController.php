<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Models\PlatformMetric;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="Platform analytics and metrics for administrators"
 * )
 */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService)
    {
        // Middleware is handled via routes
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/overview",
     *     summary="Get platform overview statistics",
     *     description="Retrieve comprehensive platform statistics for the specified time period",
     *     operationId="getPlatformOverview",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to include in statistics",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=365, example=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Platform overview retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Platform overview retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_users", type="integer"),
     *                 @OA\Property(property="total_events", type="integer"),
     *                 @OA\Property(property="total_registrations", type="integer"),
     *                 @OA\Property(property="active_users", type="integer"),
     *                 @OA\Property(property="growth_metrics", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - Admin privileges required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function overview(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $overview = $this->analyticsService->getPlatformOverview($days);

        return response()->json([
            'message' => 'Platform overview retrieved successfully',
            'data' => $overview,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/user-engagement",
     *     summary="Get user engagement analytics",
     *     description="Retrieve user engagement metrics for the specified date range",
     *     operationId="getUserEngagementAnalytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User engagement analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User engagement analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="metrics", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object")
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function userEngagement(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $metrics = PlatformMetric::ofType('user_engagement')
            ->dateRange($startDate, $endDate)
            ->orderBy('metric_date')
            ->get();

        // If no stored metrics, generate them for the requested period
        if ($metrics->isEmpty()) {
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);
            
            while ($currentDate <= $endDateCarbon) {
                $this->analyticsService->generateDailyMetrics($currentDate);
                $currentDate->addDay();
            }
            
            // Fetch the newly generated metrics
            $metrics = PlatformMetric::ofType('user_engagement')
                ->dateRange($startDate, $endDate)
                ->orderBy('metric_date')
                ->get();
        }

        return response()->json([
            'message' => 'User engagement analytics retrieved successfully',
            'data' => [
                'metrics' => $metrics,
                'summary' => $this->summarizeUserEngagement($metrics),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/event-engagement",
     *     summary="Get event engagement analytics",
     *     description="Retrieve event engagement metrics for the specified date range",
     *     operationId="getEventEngagementAnalytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event engagement analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event engagement analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="metrics", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object")
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function eventEngagement(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $metrics = PlatformMetric::ofType('event_activity')
            ->dateRange($startDate, $endDate)
            ->orderBy('metric_date')
            ->get();

        // If no stored metrics, generate them for the requested period
        if ($metrics->isEmpty()) {
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);
            
            while ($currentDate <= $endDateCarbon) {
                $this->analyticsService->generateDailyMetrics($currentDate);
                $currentDate->addDay();
            }
            
            // Fetch the newly generated metrics
            $metrics = PlatformMetric::ofType('event_activity')
                ->dateRange($startDate, $endDate)
                ->orderBy('metric_date')
                ->get();
        }

        return response()->json([
            'message' => 'Event engagement analytics retrieved successfully',
            'data' => [
                'metrics' => $metrics,
                'summary' => $this->summarizeEventEngagement($metrics),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/analytics/forum-activity",
     *     summary="Get forum activity analytics",
     *     description="Retrieve forum activity metrics for the specified date range",
     *     operationId="getForumActivityAnalytics",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analytics (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum activity analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forum activity analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="metrics", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object")
     *             )
     *         )
     *     ),
     *     security={{"sanctum":{}}}
     * )
     */
    public function forumActivity(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $metrics = PlatformMetric::ofType('forum_activity')
            ->dateRange($startDate, $endDate)
            ->orderBy('metric_date')
            ->get();

        // If no stored metrics, generate them for the requested period
        if ($metrics->isEmpty()) {
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);
            
            while ($currentDate <= $endDateCarbon) {
                $this->analyticsService->generateDailyMetrics($currentDate);
                $currentDate->addDay();
            }
            
            // Fetch the newly generated metrics
            $metrics = PlatformMetric::ofType('forum_activity')
                ->dateRange($startDate, $endDate)
                ->orderBy('metric_date')
                ->get();
        }

        return response()->json([
            'message' => 'Forum activity analytics retrieved successfully',
            'data' => [
                'metrics' => $metrics,
                'summary' => $this->summarizeForumActivity($metrics),
            ],
        ]);
    }

    /**
     * Get real-time analytics
     */
    public function realTime(Request $request): JsonResponse
    {
        $minutes = $request->input('minutes', 60);
        $since = now()->subMinutes($minutes);

        $events = AnalyticsEvent::where('occurred_at', '>=', $since)
            ->orderBy('occurred_at', 'desc')
            ->take(100)
            ->get();

        $summary = [
            'total_events' => $events->count(),
            'unique_users' => $events->pluck('user_id')->filter()->unique()->count(),
            'top_actions' => $events->groupBy('action')->map(fn($group) => $group->count())->sortDesc()->take(5),
            'event_timeline' => $events->groupBy(function ($event) {
                return $event->occurred_at->format('H:i');
            })->map(fn($group) => $group->count()),
        ];

        return response()->json([
            'message' => 'Real-time analytics retrieved successfully',
            'data' => [
                'events' => $events,
                'summary' => $summary,
                'period_minutes' => $minutes,
            ],
        ]);
    }

    /**
     * Generate daily metrics manually
     */
    public function generateDailyMetrics(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $carbonDate = Carbon::parse($date);

        $generatedMetrics = $this->analyticsService->generateDailyMetrics($carbonDate);

        return response()->json([
            'message' => 'Daily metrics generated successfully',
            'data' => [
                'date' => $date,
                'metrics' => $generatedMetrics,
            ],
        ]);
    }

    /**
     * Get analytics events with filtering
     */
    public function events(Request $request): JsonResponse
    {
        $query = AnalyticsEvent::query();

        if ($request->filled('event_type')) {
            $query->ofType($request->input('event_type'));
        }

        if ($request->filled('action')) {
            $query->action($request->input('action'));
        }

        if ($request->filled('entity_type')) {
            $query->forEntity($request->input('entity_type'), $request->input('entity_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('occurred_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('occurred_at', '<=', $request->input('end_date'));
        }

        $events = $query->with('user:id,name')
            ->orderBy('occurred_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'message' => 'Analytics events retrieved successfully',
            'data' => $events,
        ]);
    }

    /**
     * Summarize user engagement metrics
     */
    private function summarizeUserEngagement($metrics): array
    {
        $totalActiveUsers = $metrics->sum(fn($metric) => $metric->value['active_users'] ?? 0);
        $totalNewUsers = $metrics->sum(fn($metric) => $metric->value['new_users'] ?? 0);
        $averageDaily = $metrics->count() > 0 ? round($totalActiveUsers / $metrics->count(), 2) : 0;
        $peakDay = $metrics->sortByDesc(fn($metric) => $metric->value['active_users'] ?? 0)->first();

        return [
            'total_active_users' => $totalActiveUsers,
            'total_new_users' => $totalNewUsers,
            'average_daily_active' => $averageDaily,
            'peak_day' => $peakDay ? [
                'date' => $peakDay->metric_date,
                'count' => $peakDay->value['active_users'] ?? 0,
            ] : null,
        ];
    }

    /**
     * Summarize event engagement metrics
     */
    private function summarizeEventEngagement($metrics): array
    {
        $totalEvents = $metrics->sum(fn($metric) => $metric->value['events_created'] ?? 0);
        $totalRegistrations = $metrics->sum(fn($metric) => $metric->value['event_registrations'] ?? 0);
        $totalViews = $metrics->sum(fn($metric) => $metric->value['event_views'] ?? 0);
        $averageDaily = $metrics->count() > 0 ? round($totalEvents / $metrics->count(), 2) : 0;

        return [
            'total_events_created' => $totalEvents,
            'total_registrations' => $totalRegistrations,
            'total_views' => $totalViews,
            'average_daily_events' => $averageDaily,
        ];
    }

    /**
     * Summarize forum activity metrics
     */
    private function summarizeForumActivity($metrics): array
    {
        $totalPosts = $metrics->sum(fn($metric) => $metric->value['posts_created'] ?? 0);
        $totalLikes = $metrics->sum(fn($metric) => $metric->value['likes_given'] ?? 0);
        $totalActiveUsers = $metrics->sum(fn($metric) => $metric->value['active_forum_users'] ?? 0);

        return [
            'total_posts' => $totalPosts,
            'total_likes' => $totalLikes,
            'total_active_users' => $totalActiveUsers,
            'average_daily_posts' => $metrics->count() > 0 ? round($totalPosts / $metrics->count(), 2) : 0,
            'average_daily_likes' => $metrics->count() > 0 ? round($totalLikes / $metrics->count(), 2) : 0,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/analytics/generate-daily",
     *     summary="Generate daily metrics manually",
     *     description="Manually trigger the generation of daily analytics metrics for a specific date",
     *     operationId="generateDailyMetrics",
     *     tags={"Analytics"},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date to generate metrics for (YYYY-MM-DD format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-15")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daily metrics generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Daily metrics generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="metrics_generated", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="user_engagement", type="object"),
     *                 @OA\Property(property="event_activity", type="object"),
     *                 @OA\Property(property="forum_metrics", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid date format or future date",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function generateDaily(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $targetDate = Carbon::parse($request->input('date'));
        
        // Generate metrics for the specified date
        $generatedMetrics = $this->analyticsService->generateDailyMetrics($targetDate);

        return response()->json([
            'message' => 'Daily metrics generated successfully',
            'data' => [
                'date' => $targetDate->toDateString(),
                'metrics_generated' => array_keys($generatedMetrics),
                'user_engagement' => $generatedMetrics['user_engagement'] ?? [],
                'event_activity' => $generatedMetrics['event_activity'] ?? [],
                'forum_metrics' => $generatedMetrics['forum_activity'] ?? [],
            ],
        ]);
    }
}