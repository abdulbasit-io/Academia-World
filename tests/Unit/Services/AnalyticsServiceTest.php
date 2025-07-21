<?php

use App\Services\AnalyticsService;
use App\Models\AnalyticsEvent;
use App\Models\PlatformMetric;
use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->analyticsService = new AnalyticsService();
});

describe('AnalyticsService', function () {
    test('can track user action', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $result = $this->analyticsService->trackUserAction('login', [
            'entity_type' => 'user',
            'entity_id' => $user->getKey(),
        ]);

        expect($result)->toBeInstanceOf(AnalyticsEvent::class);
        expect($result->event_type)->toBe('user_action');
        expect($result->action)->toBe('login');
        expect($result->user_id)->toBe($user->getKey());
    });

    test('can track engagement metric', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $this->actingAs($user);

        $result = $this->analyticsService->trackEngagement('event_view', [
            'entity_type' => 'event',
            'entity_id' => $event->getKey(),
        ]);

        expect($result)->toBeInstanceOf(AnalyticsEvent::class);
        expect($result->event_type)->toBe('engagement_metric');
        expect($result->action)->toBe('event_view');
        expect($result->entity_type)->toBe('event');
    });

    test('can generate daily active users metric', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $today = Carbon::today();

        // Create analytics events for today
        AnalyticsEvent::factory()->create([
            'user_id' => $user1->getKey(),
            'occurred_at' => $today->copy()->addHours(2),
        ]);
        
        AnalyticsEvent::factory()->create([
            'user_id' => $user2->getKey(),
            'occurred_at' => $today->copy()->addHours(4),
        ]);

        $result = $this->analyticsService->generateDailyActiveUsers($today);

        expect($result)->toBeInstanceOf(PlatformMetric::class);
        expect($result->metric_type)->toBe('user_engagement');
        expect($result->metric_key)->toBe('daily_active_users');
        expect($result->value['count'])->toBe(2);
    });

    test('can get platform overview', function () {
        User::factory()->count(5)->create();
        Event::factory()->count(3)->create();
        DiscussionForum::factory()->create();
        ForumPost::factory()->count(2)->create();

        $overview = $this->analyticsService->getPlatformOverview(30);

        expect($overview)->toHaveKey('total_users');
        expect($overview)->toHaveKey('total_events');
        expect($overview)->toHaveKey('total_forum_posts');
        expect($overview)->toHaveKey('total_forums');
        expect($overview['total_users'])->toBe(5);
        expect($overview['total_events'])->toBe(3);
        expect($overview['total_forum_posts'])->toBe(2);
    });

    test('can get user engagement metrics', function () {
        $user = User::factory()->create();
        
        // Create some analytics events
        AnalyticsEvent::factory()->count(3)->create([
            'user_id' => $user->getKey(),
            'occurred_at' => now()->subDays(2),
        ]);

        $metrics = $this->analyticsService->getUserEngagementMetrics(7);

        expect($metrics)->toHaveKey('total_users');
        expect($metrics)->toHaveKey('active_users');
        expect($metrics)->toHaveKey('new_users');
        expect($metrics)->toHaveKey('engagement_trend');
        expect($metrics['total_users'])->toBeGreaterThanOrEqual(1);
    });

    test('can get event analytics', function () {
        Event::factory()->count(2)->create();
        
        // Create some event-related analytics
        AnalyticsEvent::factory()->create([
            'action' => 'event_view',
            'occurred_at' => now()->subDays(1),
        ]);

        $analytics = $this->analyticsService->getEventAnalytics(30);

        expect($analytics)->toHaveKey('total_events');
        expect($analytics)->toHaveKey('events_created');
        expect($analytics)->toHaveKey('event_views');
        expect($analytics)->toHaveKey('popular_events');
        expect($analytics['total_events'])->toBe(2);
    });

    test('can get platform statistics', function () {
        User::factory()->count(3)->create();
        Event::factory()->count(2)->create();

        $stats = $this->analyticsService->getPlatformStatistics();

        expect($stats)->toHaveKey('total_users');
        expect($stats)->toHaveKey('total_events');
        expect($stats)->toHaveKey('growth_metrics');
        expect($stats)->toHaveKey('platform_health');
        expect($stats['total_users'])->toBe(3);
        expect($stats['total_events'])->toBe(2);
    });

    test('can get realtime metrics', function () {
        $user = User::factory()->create();
        
        // Create recent activity
        AnalyticsEvent::factory()->create([
            'user_id' => $user->getKey(),
            'occurred_at' => now()->subMinutes(30),
            'session_id' => 'test-session-1',
        ]);

        $metrics = $this->analyticsService->getRealtimeMetrics();

        expect($metrics)->toHaveKey('active_sessions');
        expect($metrics)->toHaveKey('recent_activity');
        expect($metrics)->toHaveKey('live_events');
        expect($metrics)->toHaveKey('current_online_users');
    });
});
