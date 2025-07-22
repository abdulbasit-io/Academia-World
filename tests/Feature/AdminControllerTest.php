<?php

use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Models\AdminLog;

beforeEach(function () {
    // Create admin user
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'account_status' => 'active',
    ]);

    // Create regular user
    $this->regularUser = User::factory()->create([
        'is_admin' => false,
        'account_status' => 'active',
    ]);
});

test('non admin cannot access admin routes', function () {
    $this->actingAs($this->regularUser, 'sanctum');

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Unauthorized. Admin access required.'
        ]);
});

test('admin can access dashboard', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'overview',
                'realtime',
                'recent_admin_actions',
            ]
        ]);
});

test('admin can get analytics', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/v1/admin/analytics?days=30');

    $response->assertStatus(200)
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
    User::factory(5)->create();

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/v1/admin/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
            ]
        ]);
});

test('admin can ban user', function () {
    $userToBan = User::factory()->create(['is_banned' => false]);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/v1/admin/users/{$userToBan->uuid}/ban", [
        'reason' => 'Inappropriate behavior'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'user_id',
                'is_banned',
                'action',
            ]
        ]);

    $userToBan->refresh();
    expect($userToBan->is_banned)->toBeTrue();
    expect($userToBan->ban_reason)->toBe('Inappropriate behavior');

    // Check admin log was created
    $this->assertDatabaseHas('admin_logs', [
        'admin_id' => $this->admin->getKey(),
        'action' => 'user_ban',
        'target_type' => 'user',
        'target_id' => $userToBan->getKey(),
    ]);
});

test('admin can unban user', function () {
    $bannedUser = User::factory()->create([
        'is_banned' => true,
        'ban_reason' => 'Previous violation',
        'banned_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/v1/admin/users/{$bannedUser->uuid}/ban", [
        'reason' => 'Reviewed and lifted'
    ]);

    $response->assertStatus(200);

    $bannedUser->refresh();
    expect($bannedUser->is_banned)->toBeFalse();
    expect($bannedUser->ban_reason)->toBeNull();

    // Check admin log was created
    $this->assertDatabaseHas('admin_logs', [
        'admin_id' => $this->admin->getKey(),
        'action' => 'user_unban',
        'target_type' => 'user',
        'target_id' => $bannedUser->getKey(),
    ]);
});

test('admin can get platform health', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/v1/admin/platform-health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'database_status',
                'active_users_last_24h',
                'total_events',
                'active_events',
                'total_forum_posts',
                'total_users',
                'banned_users',
                'pending_users',
            ]
        ]);
});

test('ban user validation requires reason', function () {
    $userToBan = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/v1/admin/users/{$userToBan->uuid}/ban", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});
