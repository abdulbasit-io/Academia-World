<?php

use App\Models\User;
use App\Models\Event;
use App\Models\DiscussionForum;
use App\Models\ForumPost;
use App\Models\UserConnection;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->otherUser = User::factory()->create();
    
    $this->event = Event::factory()->create([
        'host_id' => $this->user->id,
        'title' => 'Test Conference',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(7)->addHours(4),
    ]);
});

// ====================================
// Discussion Forums Tests
// ====================================

it('can list event forums', function () {
    $forum1 = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'General Discussion',
        'type' => 'general',
        'created_by' => $this->user->id,
    ]);
    
    $forum2 = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Q&A Session',
        'type' => 'q_and_a',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/events/{$this->event->uuid}/forums");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'description',
                    'type',
                    'is_moderated',
                    'post_count',
                    'participant_count',
                    'last_activity_at',
                    'creator',
                    'created_at',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.title'))->toBe('General Discussion');
    expect($response->json('data.1.title'))->toBe('Q&A Session');
});

it('event host can create forum', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/{$this->event->uuid}/forums", [
            'title' => 'New Discussion Forum',
            'description' => 'A place for general discussion',
            'type' => 'general',
            'is_moderated' => false,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Forum created successfully',
            'data' => [
                'title' => 'New Discussion Forum',
                'description' => 'A place for general discussion',
                'type' => 'general',
                'is_moderated' => false,
            ]
        ]);

    $this->assertDatabaseHas('discussion_forums', [
        'event_id' => $this->event->id,
        'title' => 'New Discussion Forum',
        'type' => 'general',
        'created_by' => $this->user->id,
    ]);
});

it('non-host cannot create forum', function () {
    $response = $this->actingAs($this->otherUser)
        ->postJson("/api/v1/events/{$this->event->uuid}/forums", [
            'title' => 'Unauthorized Forum',
            'type' => 'general',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You are not authorized to create forums for this event'
        ]);
});

it('can view specific forum', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Test Forum',
        'description' => 'Test forum description',
        'type' => 'technical',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/forums/{$forum->uuid}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Forum retrieved successfully',
            'data' => [
                'title' => 'Test Forum',
                'description' => 'Test forum description',
                'type' => 'technical',
            ]
        ]);
});

it('event host can update forum', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Original Title',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/forums/{$forum->uuid}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'is_active' => true,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Forum updated successfully',
            'data' => [
                'title' => 'Updated Title',
                'description' => 'Updated description',
                'is_active' => true,
            ]
        ]);
});

it('event host can delete forum', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/forums/{$forum->uuid}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Forum deleted successfully'
        ]);

    $this->assertDatabaseMissing('discussion_forums', [
        'id' => $forum->id,
    ]);
});

// ====================================
// Forum Posts Tests
// ====================================

it('can list forum posts', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $post1 = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->user->id,
        'content' => 'First post content',
    ]);

    $post2 = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->otherUser->id,
        'content' => 'Second post content',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/forums/{$forum->uuid}/posts");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data',
            'pagination'
        ]);
});

it('registered user can create post in forum', function () {
    // Register user for the event first
    $this->event->registrations()->attach($this->otherUser->id, [
        'uuid' => \Illuminate\Support\Str::uuid(),
        'status' => 'registered',
        'registered_at' => now(),
    ]);

    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->otherUser)
        ->postJson("/api/v1/forums/{$forum->uuid}/posts", [
            'content' => 'This is my first post in the forum!',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Post created successfully',
            'data' => [
                'content' => 'This is my first post in the forum!',
                'likes_count' => 0,
                'replies_count' => 0,
            ]
        ]);

    $this->assertDatabaseHas('forum_posts', [
        'forum_id' => $forum->id,
        'user_id' => $this->otherUser->id,
        'content' => 'This is my first post in the forum!',
    ]);
});

it('can reply to a post', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $parentPost = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->user->id,
        'content' => 'Original post',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/forums/{$forum->uuid}/posts", [
            'content' => 'This is a reply to the original post',
            'parent_id' => $parentPost->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('forum_posts', [
        'forum_id' => $forum->id,
        'parent_id' => $parentPost->id,
        'content' => 'This is a reply to the original post',
    ]);
});

it('can view post with replies', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $post = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->user->id,
        'content' => 'Main post',
    ]);

    $reply = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->otherUser->id,
        'parent_id' => $post->id,
        'content' => 'Reply to main post',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/posts/{$post->uuid}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'content',
                'replies' => [
                    '*' => [
                        'uuid',
                        'content',
                        'user',
                        'created_at',
                    ]
                ]
            ]
        ]);

    expect($response->json('data.replies'))->toHaveCount(1);
    expect($response->json('data.replies.0.content'))->toBe('Reply to main post');
});

it('post author can update their post', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $post = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->user->id,
        'content' => 'Original content',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/posts/{$post->uuid}", [
            'content' => 'Updated content',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Post updated successfully',
            'data' => [
                'content' => 'Updated content',
            ]
        ]);

    $post->refresh();
    expect($post->content)->toBe('Updated content');
    expect($post->edited_at)->not->toBeNull();
});

it('can toggle like on post', function () {
    $forum = DiscussionForum::factory()->create([
        'event_id' => $this->event->id,
        'created_by' => $this->user->id,
    ]);

    $post = ForumPost::factory()->create([
        'forum_id' => $forum->id,
        'user_id' => $this->user->id,
        'likes_count' => 0,
    ]);

    // Like the post
    $response = $this->actingAs($this->otherUser)
        ->postJson("/api/v1/posts/{$post->uuid}/like");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Post liked',
            'data' => [
                'liked' => true,
                'likes_count' => 1,
            ]
        ]);

    // Unlike the post
    $response = $this->actingAs($this->otherUser)
        ->postJson("/api/v1/posts/{$post->uuid}/like");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Post unliked',
            'data' => [
                'liked' => false,
                'likes_count' => 0,
            ]
        ]);
});

// ====================================
// User Connections Tests
// ====================================

it('can search for users to connect with', function () {
    $searchableUser = User::factory()->create([
        'name' => 'John Smith',
        'institution' => 'Test University',
        'department' => 'Computer Science',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/users/search?query=John');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'institution',
                    'department',
                    'position',
                    'connection_status',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('John Smith');
});

it('can send connection request', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/connections', [
            'user_id' => $this->otherUser->id,
            'message' => 'Would love to connect and collaborate!',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Connection request sent successfully',
            'data' => [
                'status' => 'pending',
            ]
        ]);

    $this->assertDatabaseHas('user_connections', [
        'requester_id' => $this->user->id,
        'addressee_id' => $this->otherUser->id,
        'status' => 'pending',
        'message' => 'Would love to connect and collaborate!',
    ]);
});

it('can view pending connection requests', function () {
    UserConnection::factory()->create([
        'requester_id' => $this->otherUser->id,
        'addressee_id' => $this->user->id,
        'status' => 'pending',
        'message' => 'Hello! Would like to connect.',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/connections/pending');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id',
                    'requester',
                    'message',
                    'requested_at',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.message'))->toBe('Hello! Would like to connect.');
});

it('can accept connection request', function () {
    $connection = UserConnection::factory()->create([
        'requester_id' => $this->otherUser->id,
        'addressee_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/connections/{$connection->id}/respond", [
            'action' => 'accept',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Connection request accepted',
            'data' => [
                'status' => 'accepted',
            ]
        ]);

    $connection->refresh();
    expect($connection->status)->toBe('accepted');
    expect($connection->responded_at)->not->toBeNull();
});

it('can decline connection request', function () {
    $connection = UserConnection::factory()->create([
        'requester_id' => $this->otherUser->id,
        'addressee_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/connections/{$connection->id}/respond", [
            'action' => 'decline',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Connection request declined',
            'data' => [
                'status' => 'declined',
            ]
        ]);
});

it('can view connections list', function () {
    $connection = UserConnection::factory()->create([
        'requester_id' => $this->user->id,
        'addressee_id' => $this->otherUser->id,
        'status' => 'accepted',
        'responded_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/connections');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'user',
                    'connected_at',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(1);
});

it('prevents duplicate connection requests', function () {
    UserConnection::factory()->create([
        'requester_id' => $this->user->id,
        'addressee_id' => $this->otherUser->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/connections', [
            'user_id' => $this->otherUser->id,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Connection request already pending'
        ]);
});

it('prevents connecting to self', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/connections', [
            'user_id' => $this->user->id,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'You cannot connect to yourself'
        ]);
});
