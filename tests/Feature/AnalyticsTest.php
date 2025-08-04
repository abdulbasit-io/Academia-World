<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\AnalyticsEvent;
use App\Models\AdminLog;
use App\Models\PlatformMetric;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $analyticsService;
    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->analyticsService = app(AnalyticsService::class);
        
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create();
    }

    public function test_can_track_user_action()
    {
        $this->analyticsService->trackUserAction(
            $this->user->id,
            'page_view',
            'events.index',
            ['page' => 1]
        );

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $this->user->id,
            'event_name' => 'page_view',
            'event_category' => 'events.index',
        ]);
    }

    public function test_can_get_user_engagement_metrics()
    {
        // Create some analytics events
        AnalyticsEvent::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'event_name' => 'page_view',
            'created_at' => now()->subDays(1),
        ]);

        AnalyticsEvent::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'event_name' => 'event_register',
            'created_at' => now()->subDays(2),
        ]);

        $metrics = $this->analyticsService->getUserEngagementMetrics(7);

        $this->assertArrayHasKey('total_users', $metrics);
        $this->assertArrayHasKey('active_users', $metrics);
        $this->assertArrayHasKey('avg_session_duration', $metrics);
    }

    public function test_can_get_event_analytics()
    {
        $event = Event::factory()->create();
        
        // Create analytics for the event
        AnalyticsEvent::factory()->count(10)->create([
            'event_name' => 'event_view',
            'event_data' => ['event_id' => $event->uuid],
        ]);

        $analytics = $this->analyticsService->getEventAnalytics(30);

        $this->assertArrayHasKey('total_events', $analytics);
        $this->assertArrayHasKey('event_views', $analytics);
        $this->assertArrayHasKey('popular_events', $analytics);
    }

    public function test_can_get_platform_statistics()
    {
        // Create some platform metrics
        PlatformMetric::create([
            'metric_type' => 'user_engagement',
            'metric_key' => 'daily_active_users',
            'value' => ['count' => 150],
            'metric_date' => now()->format('Y-m-d'),
        ]);

        $stats = $this->analyticsService->getPlatformStatistics();

        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('total_events', $stats);
        $this->assertArrayHasKey('growth_metrics', $stats);
    }

    public function test_can_get_realtime_metrics()
    {
        // Create recent analytics events
        AnalyticsEvent::factory()->count(5)->create([
            'created_at' => now()->subMinutes(10),
        ]);

        $metrics = $this->analyticsService->getRealtimeMetrics();

        $this->assertArrayHasKey('active_sessions', $metrics);
        $this->assertArrayHasKey('recent_activity', $metrics);
        $this->assertArrayHasKey('live_events', $metrics);
    }

    public function test_admin_can_access_analytics_overview()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_users',
                    'total_events',
                    'active_users_today',
                    'growth_metrics',
                ],
            ]);
    }

    public function test_admin_can_access_user_engagement_analytics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics/users?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_users',
                    'active_users',
                    'user_growth',
                ],
            ]);
    }

    public function test_admin_can_access_event_analytics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics/events?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_events',
                    'event_views',
                    'popular_events',
                ],
            ]);
    }

    public function test_admin_can_access_realtime_metrics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics/realtime');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'active_sessions',
                    'recent_activity',
                    'live_events',
                ],
            ]);
    }

    public function test_regular_user_cannot_access_analytics()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_analytics()
    {
        $response = $this->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(401);
    }

    public function test_can_update_platform_metrics()
    {
        $this->analyticsService->updatePlatformMetrics();

        $this->assertDatabaseHas('platform_metrics', [
            'metric_name' => 'total_users',
            'metric_date' => now()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('platform_metrics', [
            'metric_name' => 'total_events',
            'metric_date' => now()->format('Y-m-d'),
        ]);
    }

    public function test_can_export_analytics_data()
    {
        AnalyticsEvent::factory()->count(10)->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/analytics/export', [
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'metrics' => ['user_actions', 'events'],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'export_id',
                    'download_url',
                ],
            ]);
    }

    public function test_analytics_service_handles_edge_cases()
    {
        // Test with no data
        $metrics = $this->analyticsService->getUserEngagementMetrics(7);
        $this->assertEquals(0, $metrics['active_users']);

        // Test with invalid date range
        $analytics = $this->analyticsService->getEventAnalytics(0);
        $this->assertIsArray($analytics);
    }
}
