<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class AdminModerationTest extends TestCase
{
    #[Test]
    public function admin_can_ban_event()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published',
            'title' => 'Event to be banned'
        ]);

        $banData = [
            'reason' => 'Inappropriate content detected'
        ];

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", $banData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Event banned successfully'
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'banned',
            'ban_reason' => 'Inappropriate content detected',
            'banned_by' => $admin->id
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'banned_at' => $event->fresh()->banned_at
        ]);
    }

    #[Test]
    public function non_admin_cannot_ban_event()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        $banData = [
            'reason' => 'Inappropriate content detected'
        ];

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", $banData, $this->getApiHeaders());

        $response->assertStatus(403);
        
        // Verify event wasn't banned
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
            'ban_reason' => null
        ]);
    }

    #[Test]
    public function ban_event_requires_reason()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", [], $this->getApiHeaders());

        $this->assertValidationError($response, ['reason']);
    }

    #[Test]
    public function admin_can_unban_event()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned',
            'ban_reason' => 'Previous violation',
            'banned_at' => now(),
            'banned_by' => $admin->id
        ]);

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/unban", [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Event unbanned successfully'
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
            'ban_reason' => null,
            'banned_at' => null,
            'banned_by' => null
        ]);
    }

    #[Test]
    public function non_admin_cannot_unban_event()
    {
        $user = $this->authenticateUser();
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned',
            'ban_reason' => 'Previous violation',
            'banned_by' => $admin->id
        ]);

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/unban", [], $this->getApiHeaders());

        $response->assertStatus(403);
        
        // Verify event remains banned
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'banned'
        ]);
    }

    #[Test]
    public function admin_can_force_delete_event()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned'
        ]);

        // Add some registrations
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $event->registrations()->attach($user->id, [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $response = $this->deleteJson("/api/v1/admin/events/{$event->uuid}/force-delete", [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Event permanently deleted'
        ]);

        // Verify event and all related data is deleted
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('event_registrations', ['event_id' => $event->id]);
    }

    #[Test]
    public function non_admin_cannot_force_delete_event()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned'
        ]);

        $response = $this->deleteJson("/api/v1/admin/events/{$event->uuid}/force-delete", [], $this->getApiHeaders());

        $response->assertStatus(403);
        
        // Verify event still exists
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    #[Test]
    public function admin_endpoints_require_authentication()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $endpoints = [
            ['POST', "/api/v1/admin/events/{$event->uuid}/ban", ['reason' => 'test']],
            ['POST', "/api/v1/admin/events/{$event->uuid}/unban", []],
            ['DELETE', "/api/v1/admin/events/{$event->uuid}/force-delete", []]
        ];

        foreach ($endpoints as [$method, $endpoint, $data]) {
            $response = $this->json($method, $endpoint, $data, $this->getApiHeaders());
            $response->assertStatus(401);
        }
    }

    #[Test]
    public function admin_can_ban_event_with_long_reason()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        $longReason = str_repeat('This is a detailed reason for banning. ', 20);

        $banData = [
            'reason' => $longReason
        ];

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", $banData, $this->getApiHeaders());

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'ban_reason' => $longReason
        ]);
    }

    #[Test]
    public function admin_cannot_ban_already_banned_event()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned',
            'ban_reason' => 'Already banned'
        ]);

        $banData = [
            'reason' => 'New ban reason'
        ];

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", $banData, $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Event is already banned'
        ]);
    }

    #[Test]
    public function admin_cannot_unban_non_banned_event()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/unban", [], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Event is not currently banned'
        ]);
    }

    #[Test]
    public function admin_actions_return_event_data()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published',
            'title' => 'Test Event'
        ]);

        $banData = [
            'reason' => 'Test ban reason'
        ];

        $response = $this->postJson("/api/v1/admin/events/{$event->uuid}/ban", $banData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'title',
                'status',
                'ban_reason',
                'banned_at',
                'banned_by'
            ]
        ]);

        $response->assertJson([
            'data' => [
                'title' => 'Test Event',
                'status' => 'banned',
                'ban_reason' => 'Test ban reason'
            ]
        ]);
    }

    #[Test]
    public function admin_ban_handles_nonexistent_event()
    {
        $admin = $this->authenticateAdmin();

        $banData = [
            'reason' => 'Test reason'
        ];

        $response = $this->postJson('/api/v1/admin/events/nonexistent-uuid/ban', $banData, $this->getApiHeaders());

        $response->assertStatus(404);
    }

    #[Test]
    public function force_delete_removes_all_associated_data()
    {
        $admin = $this->authenticateAdmin();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned',
            'poster' => 'event-posters/test.jpg'
        ]);

        // Add registrations
        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            $event->registrations()->attach($user->id, [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now(),
                'notes' => 'Test registration'
            ]);
        }

        $eventId = $event->id;

        $response = $this->deleteJson("/api/v1/admin/events/{$event->uuid}/force-delete", [], $this->getApiHeaders());

        $response->assertStatus(200);

        // Verify all related data is removed
        $this->assertDatabaseMissing('events', ['id' => $eventId]);
        $this->assertDatabaseMissing('event_registrations', ['event_id' => $eventId]);
        
        // Verify no orphaned registration records
        $this->assertEquals(0, DB::table('event_registrations')->where('event_id', $eventId)->count());
    }
}
