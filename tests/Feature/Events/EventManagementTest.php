<?php

namespace Tests\Feature\Events;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\Jobs\SendAdminNotification;
use App\Mail\EventRegistrationConfirmation;
use PHPUnit\Framework\Attributes\Test;

class EventManagementTest extends TestCase
{
    #[Test]
    public function guest_can_browse_public_events()
    {
        // Create some published events
        Event::factory()->count(5)->create([
            'status' => 'published',
            'visibility' => 'public'
        ]);

        // Create some private/draft events (should not appear)
        Event::factory()->count(3)->create([
            'status' => 'draft',
            'visibility' => 'private'
        ]);

        $response = $this->getJson('/api/v1/events', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'description',
                    'start_date',
                    'end_date',
                    'location_type',
                    'capacity',
                    'status',
                    'visibility',
                    'host'
                ]
            ],
            'pagination'
        ]);

        // Should only return published public events
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function authenticated_user_can_create_event()
    {
        Queue::fake();
        $user = $this->authenticateUser();

        $eventData = [
            'title' => 'AI in Academic Research',
            'description' => 'Workshop on implementing AI tools in academic research',
            'start_date' => '2025-08-15 14:00:00',
            'end_date' => '2025-08-15 17:00:00',
            'timezone' => 'UTC',
            'location_type' => 'hybrid',
            'location' => 'University Main Hall',
            'virtual_link' => 'https://zoom.us/j/123456789',
            'capacity' => 50,
            'visibility' => 'public',
            'tags' => ['AI', 'Research', 'Workshop']
        ];

        $response = $this->postJson('/api/v1/events', $eventData, $this->getApiHeaders());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'title',
                'description',
                'start_date',
                'end_date',
                'location_type',
                'status',
                'host'
            ]
        ]);

        $this->assertDatabaseHas('events', [
            'title' => 'AI in Academic Research',
            'host_id' => $user->id,
            'status' => 'published', // Auto-published
            'location_type' => 'hybrid'
        ]);

        // Verify admin notification was queued
        Queue::assertPushed(SendAdminNotification::class);
    }

    #[Test]
    public function event_creation_fails_with_invalid_data()
    {
        $user = $this->authenticateUser();

        $invalidEventData = [
            'title' => '', // Required
            'description' => '', // Required
            'start_date' => now()->addDays(2)->format('Y-m-d H:i:s'), // Valid date
            'end_date' => now()->addDays(1)->format('Y-m-d H:i:s'), // Before start_date - should trigger validation
            'location_type' => 'invalid-type',
            'capacity' => -1 // Invalid
        ];

        $response = $this->postJson('/api/v1/events', $invalidEventData, $this->getApiHeaders());

        $this->assertValidationError($response, [
            'title', 'description', 'end_date', 'location_type', 'capacity', 'visibility'
        ]);
    }

    #[Test]
    public function user_can_view_specific_event()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published',
            'visibility' => 'public'
        ]);

        $response = $this->getJson("/api/v1/events/{$event->uuid}", $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'event' => [
                    'uuid',
                    'title',
                    'description',
                    'start_date',
                    'end_date',
                    'host'
                ],
                'registration_count',
                'available_spots',
                'is_full',
                'user_registered'
            ]
        ]);
    }

    #[Test]
    public function event_owner_can_update_event()
    {
        $user = $this->authenticateUser();
        $event = Event::factory()->create([
            'host_id' => $user->id,
            'title' => 'Original Title',
            'capacity' => 30
        ]);

        $updateData = [
            'title' => 'Updated Event Title',
            'capacity' => 50,
            'description' => 'Updated description'
        ];

        $response = $this->putJson("/api/v1/events/{$event->uuid}", $updateData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Event updated successfully'
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Event Title',
            'capacity' => 50
        ]);
    }

    #[Test]
    public function non_owner_cannot_update_event()
    {
        $owner = User::factory()->create();
        $otherUser = $this->authenticateUser();
        
        $event = Event::factory()->create(['host_id' => $owner->id]);

        $updateData = ['title' => 'Unauthorized Update'];

        $response = $this->putJson("/api/v1/events/{$event->uuid}", $updateData, $this->getApiHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized to edit this event'
        ]);
    }

    #[Test]
    public function event_owner_can_delete_event()
    {
        Storage::fake('public');
        
        $user = $this->authenticateUser();
        $event = Event::factory()->create([
            'host_id' => $user->id,
            'poster' => 'test-poster.jpg'
        ]);

        $response = $this->deleteJson("/api/v1/events/{$event->uuid}", [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Event deleted successfully'
        ]);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    #[Test]
    public function user_can_register_for_event()
    {
        Mail::fake();
        Queue::fake();
        
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published',
            'capacity' => 50
        ]);

        $registrationData = [
            'notes' => 'Looking forward to this workshop!'
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/register", $registrationData, $this->getApiHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Successfully registered for the event!'
        ]);

        $this->assertDatabaseHas('event_registrations', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'registered'
        ]);

        // Verify confirmation email was sent
        Mail::assertQueued(EventRegistrationConfirmation::class);
        
        // Verify admin notification was queued
        Queue::assertPushed(SendAdminNotification::class);
    }

    #[Test]
    public function user_cannot_register_for_own_event()
    {
        $user = $this->authenticateUser();
        $event = Event::factory()->create([
            'host_id' => $user->id,
            'status' => 'published'
        ]);

        $response = $this->postJson("/api/v1/events/{$event->uuid}/register", [], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'You cannot register for your own event'
        ]);
    }

    #[Test]
    public function user_cannot_register_for_full_event()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published',
            'capacity' => 1
        ]);

        // Fill the event
        $otherUser = User::factory()->create();
        $event->registrations()->attach($otherUser->id, [
            'uuid' => Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);

        $response = $this->postJson("/api/v1/events/{$event->uuid}/register", [], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Event is full'
        ]);
    }

    #[Test]
    public function user_cannot_register_twice_for_same_event()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        // First registration
        $event->registrations()->attach($user->id, [
            'uuid' => Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);

        $response = $this->postJson("/api/v1/events/{$event->uuid}/register", [], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'You are already registered for this event'
        ]);
    }

    #[Test]
    public function user_can_unregister_from_event()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        // Register first
        $event->registrations()->attach($user->id, [
            'uuid' => Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);

        $response = $this->deleteJson("/api/v1/events/{$event->uuid}/unregister", [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Successfully unregistered from the event'
        ]);

        $this->assertDatabaseMissing('event_registrations', [
            'user_id' => $user->id,
            'event_id' => $event->id
        ]);
    }

    #[Test]
    public function user_cannot_unregister_if_not_registered()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        $response = $this->deleteJson("/api/v1/events/{$event->uuid}/unregister", [], $this->getApiHeaders());

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'You are not registered for this event'
        ]);
    }

    #[Test]
    public function user_can_get_their_hosted_events()
    {
        $user = $this->authenticateUser();
        $otherUser = User::factory()->create();

        // Create events hosted by the user
        Event::factory()->count(3)->create(['host_id' => $user->id]);
        
        // Create events hosted by others
        Event::factory()->count(2)->create(['host_id' => $otherUser->id]);

        $response = $this->getJson('/api/v1/my-events', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'start_date',
                    'status',
                    'registrations'
                ]
            ],
            'pagination'
        ]);

        // Should only return user's events
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function user_can_get_their_registrations()
    {
        $user = $this->authenticateUser();
        $host = User::factory()->create();

        // Create events and register for them
        $events = Event::factory()->count(3)->create([
            'host_id' => $host->id,
            'status' => 'published'
        ]);

        foreach ($events as $event) {
            $event->registrations()->attach($user->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $response = $this->getJson('/api/v1/my-registrations', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'start_date',
                    'host'
                ]
            ],
            'pagination'
        ]);

        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function event_host_can_view_attendees()
    {
        $host = $this->authenticateUser();
        $event = Event::factory()->create(['host_id' => $host->id]);

        // Add some attendees
        $attendees = User::factory()->count(3)->create();
        foreach ($attendees as $attendee) {
            $event->registrations()->attach($attendee->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $response = $this->getJson("/api/v1/events/{$event->uuid}/attendees", $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'institution',
                    'position'
                ]
            ],
            'total_count'
        ]);

        $this->assertEquals(3, $response->json('total_count'));
    }

    #[Test]
    public function non_host_cannot_view_attendees()
    {
        $host = User::factory()->create();
        $otherUser = $this->authenticateUser();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $response = $this->getJson("/api/v1/events/{$event->uuid}/attendees", $this->getApiHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized to view attendees'
        ]);
    }

    #[Test]
    public function events_can_be_searched_and_filtered()
    {
        // Clear any existing events to ensure clean test environment
        Event::query()->delete();
        
        // Create various events
        Event::factory()->create([
            'title' => 'AI Workshop',
            'status' => 'published',
            'visibility' => 'public',
            'location_type' => 'virtual',
            'tags' => ['AI', 'Technology']
        ]);

        Event::factory()->create([
            'title' => 'Machine Learning Conference',
            'status' => 'published',
            'visibility' => 'public',
            'location_type' => 'physical',
            'tags' => ['ML', 'Conference']
        ]);

        // Search by title
        $response = $this->getJson('/api/v1/events?search=AI', $this->getApiHeaders());
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by location type
        $response = $this->getJson('/api/v1/events?location_type=virtual', $this->getApiHeaders());
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function poster_upload_works_correctly()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }
        
        Storage::fake('public');
        $user = $this->authenticateUser();

        $eventData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_date' => '2025-08-15 14:00:00',
            'end_date' => '2025-08-15 17:00:00',
            'location_type' => 'virtual',
            'virtual_link' => 'https://zoom.us/j/123456789',
            'visibility' => 'public',
            'poster' => UploadedFile::fake()->image('poster.jpg', 800, 600)
        ];

        $response = $this->postJson('/api/v1/events', $eventData, [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(201);
        
        $event = Event::latest()->first();
        $this->assertNotNull($event->poster);
        $this->assertTrue(Storage::disk('public')->exists($event->poster));
    }
}
