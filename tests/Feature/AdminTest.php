<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\ForumPost;
use App\Models\AdminLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create();
    }

    public function test_admin_can_access_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_users',
                    'total_events',
                    'total_forums',
                    'total_posts',
                    'recent_users',
                    'recent_events',
                    'recent_admin_actions',
                ],
            ]);
    }

    public function test_admin_can_get_users_list()
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_search_users()
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users?search=John');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'John Doe');
    }

    public function test_admin_can_ban_user()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$this->user->id}/ban", [
                'reason' => 'Inappropriate behavior',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'is_banned' => true,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_ban',
            'target_id' => $this->user->id,
        ]);
    }

    public function test_admin_can_unban_user()
    {
        $this->user->update(['is_banned' => true]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/users/{$this->user->id}/ban");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'is_banned' => false,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_unban',
            'target_id' => $this->user->id,
        ]);
    }

    public function test_admin_cannot_ban_another_admin()
    {
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$otherAdmin->id}/ban", [
                'reason' => 'Test reason',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_promote_user()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$this->user->id}/promote");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_promote',
            'target_id' => $this->user->id,
        ]);
    }

    public function test_admin_can_demote_user()
    {
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$otherAdmin->id}/demote");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'is_admin' => false,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_demote',
            'target_id' => $otherAdmin->id,
        ]);
    }

    public function test_admin_cannot_demote_themselves()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$this->admin->id}/demote");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_forum_post()
    {
        $post = ForumPost::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/posts/{$post->id}", [
                'reason' => 'Inappropriate content',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('forum_posts', [
            'id' => $post->id,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'content_delete',
            'target_id' => $post->id,
        ]);
    }

    public function test_admin_can_view_logs()
    {
        AdminLog::factory()->count(5)->create([
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_filter_logs()
    {
        AdminLog::factory()->create([
            'admin_id' => $this->admin->id,
            'action' => 'user_ban',
        ]);

        AdminLog::factory()->create([
            'admin_id' => $this->admin->id,
            'action' => 'user_promote',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/logs?action=user_ban');

        $response->assertStatus(200);
        
        $logs = $response->json('data.data');
        $this->assertCount(1, $logs);
        $this->assertEquals('user_ban', $logs[0]['action']);
    }

    public function test_admin_can_create_admin()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'admin_create',
        ]);
    }

    public function test_regular_user_cannot_access_admin_routes()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_routes()
    {
        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_admin_ban_requires_reason()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$this->user->id}/ban", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_create_validates_input()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/users', [
                'name' => '',
                'email' => 'invalid-email',
                'password' => '123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_admin_logs_are_created_for_all_actions()
    {
        // Ban user
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$this->user->id}/ban", [
                'reason' => 'Test reason',
            ]);

        // Promote user  
        $newUser = User::factory()->create();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/users/{$newUser->id}/promote");

        // Check logs were created
        $this->assertDatabaseCount('admin_logs', 2);
        
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_ban',
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'user_promote',
        ]);
    }
}
