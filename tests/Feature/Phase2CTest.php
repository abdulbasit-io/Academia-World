<?php

use App\Models\User;
use App\Models\Event;
use App\Models\AnalyticsEvent;
use App\Models\PlatformMetric;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin User',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.com',
        'password' => 'password',
        'institution' => 'Test University',
        'is_admin' => true,
        'account_status' => 'active',
    ]);
    
    $this->analyticsService = new AnalyticsService();
});

describe('Phase 2C Analytics and Admin Features', function () {
    test('analytics service can generate platform overview', function () {
        // Create some test data
        User::create([
            'name' => 'Test User 1',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test1@example.com',
            'password' => 'password',
            'institution' => 'Test University',
            'account_status' => 'active',
        ]);

        $overview = $this->analyticsService->getPlatformOverview(30);

        expect($overview)->toHaveKey('total_users');
        expect($overview)->toHaveKey('total_events');
        expect($overview)->toHaveKey('total_forum_posts');
        expect($overview['total_users'])->toBeGreaterThanOrEqual(2); // admin + test user
    });

    test('analytics service can track user actions', function () {
        $user = User::create([
            'name' => 'Test User 2',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test2@example.com',
            'password' => 'password',
            'institution' => 'Test University',
            'account_status' => 'active',
        ]);

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

    test('admin can access dashboard endpoint', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'overview',
                    'realtime',
                    'recent_admin_actions',
                ]
            ]);
    });

    test('admin can access analytics endpoint', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user_engagement',
                    'event_analytics',
                    'platform_statistics',
                    'period_days',
                ]
            ]);
    });

    test('admin can list users', function () {
        // Create a test user
        User::create([
            'name' => 'Regular User 3',
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'regular3@example.com',
            'password' => 'password',
            'institution' => 'Test University',
            'account_status' => 'active',
            'is_admin' => false,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    // Links and meta may or may not be present depending on pagination
                ]
            ]);
    });

    test('admin can ban and unban users', function () {
        $targetUser = User::create([
            'name' => 'Target User 4',
            'first_name' => 'Target',
            'last_name' => 'User',
            'email' => 'target4@example.com',
            'password' => 'password',
            'institution' => 'Test University',
            'account_status' => 'active',
            'is_banned' => false,
        ]);

        // Ban user
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/users/{$targetUser->uuid}/ban", [
                'reason' => 'Test ban reason'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_banned', true);

        $targetUser->refresh();
        expect($targetUser->is_banned)->toBe(true);

        // Unban user
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/users/{$targetUser->uuid}/ban", [
                'reason' => 'Test unban reason'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_banned', false);

        $targetUser->refresh();
        expect($targetUser->is_banned)->toBe(false);
    });

    test('admin can get platform health metrics', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/platform-health')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'database_status',
                    'active_users_last_24h',
                    'total_events',
                    'total_users',
                ]
            ]);
    });

    test('non-admin cannot access admin endpoints', function () {
        $regularUser = User::create([
            'name' => 'Regular User 5',
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'regular5@example.com',
            'password' => 'password',
            'institution' => 'Test University',
            'account_status' => 'active',
            'is_admin' => false,
        ]);

        $this->actingAs($regularUser)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);

        $this->actingAs($regularUser)
            ->getJson('/api/v1/admin/analytics')
            ->assertStatus(403);
    });
});
