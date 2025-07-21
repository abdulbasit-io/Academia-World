<?php

use App\Models\User;
use App\Models\Event;
use App\Models\ForumPost;
use App\Models\DiscussionForum;
use App\Models\AdminLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

describe('Admin Dashboard', function () {
    test('admin can access dashboard', function () {
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

    test('non-admin cannot access dashboard', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);
    });
});

describe('Admin Analytics', function () {
    test('admin can get analytics data', function () {
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

    test('admin can get analytics for specific period', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/analytics?days=7')
            ->assertStatus(200)
            ->assertJsonPath('data.period_days', 7);
    });
});

describe('User Management', function () {
    test('admin can list users', function () {
        User::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'links',
                    'meta',
                ]
            ]);
    });

    test('admin can search users', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        User::factory()->create(['name' => 'Jane Smith']);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users?search=John')
            ->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'John Doe');
    });

    test('admin can ban user', function () {
        $targetUser = User::factory()->create(['is_banned' => false]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/users/{$targetUser->uuid}/ban", [
                'reason' => 'Violating community guidelines'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_banned', true);

        $targetUser->refresh();
        expect($targetUser->is_banned)->toBe(true);
        expect($targetUser->ban_reason)->toBe('Violating community guidelines');

        // Check admin log was created
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->getKey(),
            'action' => 'user_ban',
            'target_type' => 'user',
            'target_id' => $targetUser->getKey(),
        ]);
    });

    test('admin can unban user', function () {
        $targetUser = User::factory()->create([
            'is_banned' => true,
            'ban_reason' => 'Previous violation'
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/users/{$targetUser->uuid}/ban", [
                'reason' => 'Appeal approved'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_banned', false);

        $targetUser->refresh();
        expect($targetUser->is_banned)->toBe(false);
        expect($targetUser->ban_reason)->toBeNull();
    });
});

describe('Event Management', function () {
    test('admin can list events', function () {
        Event::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/events')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'links',
                    'meta',
                ]
            ]);
    });

    test('admin can update event status', function () {
        $event = Event::factory()->create(['status' => 'published']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/events/{$event->uuid}/status", [
                'status' => 'cancelled',
                'reason' => 'Venue unavailable'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.new_status', 'cancelled');

        $event->refresh();
        expect($event->status)->toBe('cancelled');

        // Check admin log was created
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->getKey(),
            'action' => 'event_status_change',
        ]);
    });

    test('admin can delete event', function () {
        $event = Event::factory()->create();
        $eventUuid = $event->uuid;

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/events/{$event->uuid}", [
                'reason' => 'Inappropriate content'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'deleted');

        $this->assertDatabaseMissing('events', ['uuid' => $eventUuid]);

        // Check admin log was created
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->getKey(),
            'action' => 'event_delete',
        ]);
    });
});

describe('Forum Management', function () {
    test('admin can list forum posts', function () {
        $forum = DiscussionForum::factory()->create();
        ForumPost::factory()->count(3)->create(['forum_id' => $forum->id]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/forum-posts')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'links',
                    'meta',
                ]
            ]);
    });

    test('admin can delete forum post', function () {
        $forum = DiscussionForum::factory()->create();
        $post = ForumPost::factory()->create(['forum_id' => $forum->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/forum-posts/{$post->id}", [
                'reason' => 'Spam content'
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'deleted');

        $this->assertDatabaseMissing('forum_posts', ['id' => $post->id]);

        // Check admin log was created
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->getKey(),
            'action' => 'post_delete',
        ]);
    });
});

describe('Platform Health', function () {
    test('admin can get platform health metrics', function () {
        User::factory()->count(10)->create();
        Event::factory()->count(5)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/platform-health')
            ->assertStatus(200)
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
});

describe('Admin Logs', function () {
    test('admin can view admin logs', function () {
        AdminLog::factory()->count(5)->create(['admin_id' => $this->admin->getKey()]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/logs')
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'links',
                    'meta',
                ]
            ]);
    });

    test('admin can filter logs by action', function () {
        AdminLog::factory()->create(['admin_id' => $this->admin->getKey(), 'action' => 'user_ban']);
        AdminLog::factory()->create(['admin_id' => $this->admin->getKey(), 'action' => 'event_delete']);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/logs?action=user_ban')
            ->assertStatus(200);
    });
});
