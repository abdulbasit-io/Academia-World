<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class EventModelTest extends TestCase
{
    #[Test]
    public function event_has_uuid_generated_on_creation()
    {
        $event = Event::factory()->create();
        
        $this->assertNotNull($event->uuid);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($event->uuid));
    }

    #[Test]
    public function event_belongs_to_host()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $this->assertInstanceOf(User::class, $event->host);
        $this->assertEquals($host->id, $event->host->id);
    }

    #[Test]
    public function event_has_registrations_relationship()
    {
        $event = Event::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $event->registrations()->attach($user->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $this->assertCount(3, $event->registrations);
        $this->assertInstanceOf(User::class, $event->registrations->first());
    }

    #[Test]
    public function event_published_scope_works()
    {
        Event::factory()->count(3)->create(['status' => 'published']);
        Event::factory()->count(2)->create(['status' => 'draft']);
        Event::factory()->count(1)->create(['status' => 'banned']);

        $publishedEvents = Event::published()->get();

        $this->assertCount(3, $publishedEvents);
        $publishedEvents->each(function ($event) {
            $this->assertEquals('published', $event->status);
        });
    }

    #[Test]
    public function event_active_scope_works()
    {
        Event::factory()->count(2)->create(['status' => 'published']);
        Event::factory()->count(1)->create(['status' => 'completed']);
        Event::factory()->count(2)->create(['status' => 'draft']);
        Event::factory()->count(1)->create(['status' => 'banned']);

        $activeEvents = Event::active()->get();

        $this->assertCount(3, $activeEvents);
        $activeEvents->each(function ($event) {
            $this->assertContains($event->status, ['published', 'completed']);
        });
    }

    #[Test]
    public function event_banned_scope_works()
    {
        Event::factory()->count(2)->create(['status' => 'published']);
        Event::factory()->count(3)->create(['status' => 'banned']);

        $bannedEvents = Event::banned()->get();

        $this->assertCount(3, $bannedEvents);
        $bannedEvents->each(function ($event) {
            $this->assertEquals('banned', $event->status);
        });
    }

    #[Test]
    public function event_public_scope_works()
    {
        Event::factory()->count(3)->create(['visibility' => 'public']);
        Event::factory()->count(2)->create(['visibility' => 'private']);

        $publicEvents = Event::public()->get();

        $this->assertCount(3, $publicEvents);
        $publicEvents->each(function ($event) {
            $this->assertEquals('public', $event->visibility);
        });
    }

    #[Test]
    public function event_upcoming_scope_works()
    {
        Event::factory()->count(2)->create(['start_date' => now()->addDays(5)]);
        Event::factory()->count(3)->create(['start_date' => now()->subDays(5)]);

        $upcomingEvents = Event::upcoming()->get();

        $this->assertCount(2, $upcomingEvents);
        $upcomingEvents->each(function ($event) {
            $this->assertTrue($event->start_date->gt(now()));
        });
    }

    #[Test]
    public function event_find_by_uuid_works()
    {
        $event = Event::factory()->create();

        $foundEvent = Event::findByUuid($event->uuid);

        $this->assertEquals($event->id, $foundEvent->id);
    }

    #[Test]
    public function event_find_by_uuid_returns_null_for_invalid_uuid()
    {
        $foundEvent = Event::findByUuid('invalid-uuid');

        $this->assertNull($foundEvent);
    }

    #[Test]
    public function event_route_key_is_uuid()
    {
        $event = Event::factory()->create();

        $this->assertEquals('uuid', $event->getRouteKeyName());
    }

    #[Test]
    public function event_is_full_attribute_works()
    {
        $event = Event::factory()->create(['capacity' => 2]);
        $users = User::factory()->count(3)->create();

        // Not full initially
        $this->assertFalse($event->is_full);

        // Add registrations up to capacity
        $event->registrations()->attach($users[0]->id, [
            'uuid' => Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);
        $event->registrations()->attach($users[1]->id, [
            'uuid' => Str::uuid()->toString(),
            'status' => 'registered',
            'registered_at' => now()
        ]);

        $event->refresh();
        $this->assertTrue($event->is_full);
    }

    #[Test]
    public function event_available_spots_attribute_works()
    {
        $event = Event::factory()->create(['capacity' => 5]);
        $users = User::factory()->count(2)->create();

        $this->assertEquals(5, $event->available_spots);

        // Add some registrations
        foreach ($users as $user) {
            $event->registrations()->attach($user->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $event->refresh();
        $this->assertEquals(3, $event->available_spots);
    }

    #[Test]
    public function event_unlimited_capacity_works()
    {
        $event = Event::factory()->create(['capacity' => null]);
        $users = User::factory()->count(100)->create();

        foreach ($users as $user) {
            $event->registrations()->attach($user->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $event->refresh();
        $this->assertFalse($event->is_full);
        $this->assertEquals(PHP_INT_MAX, $event->available_spots);
    }

    #[Test]
    public function event_can_be_edited_by_host()
    {
        $host = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);

        $this->assertTrue($event->canBeEditedBy($host));
        $this->assertFalse($event->canBeEditedBy($otherUser));
    }

    #[Test]
    public function banned_event_cannot_be_edited()
    {
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'host_id' => $host->id,
            'status' => 'banned'
        ]);

        $this->assertFalse($event->canBeEditedBy($host));
    }

    #[Test]
    public function event_can_be_moderated_by_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create();

        $this->assertTrue($event->canBeModeratedBy($admin));
        $this->assertFalse($event->canBeModeratedBy($regularUser));
    }

    #[Test]
    public function event_is_active_method_works()
    {
        $publishedEvent = Event::factory()->create(['status' => 'published']);
        $completedEvent = Event::factory()->create(['status' => 'completed']);
        $draftEvent = Event::factory()->create(['status' => 'draft']);
        $bannedEvent = Event::factory()->create(['status' => 'banned']);

        $this->assertTrue($publishedEvent->isActive());
        $this->assertTrue($completedEvent->isActive());
        $this->assertFalse($draftEvent->isActive());
        $this->assertFalse($bannedEvent->isActive());
    }

    #[Test]
    public function event_is_banned_method_works()
    {
        $publishedEvent = Event::factory()->create(['status' => 'published']);
        $bannedEvent = Event::factory()->create(['status' => 'banned']);

        $this->assertFalse($publishedEvent->isBanned());
        $this->assertTrue($bannedEvent->isBanned());
    }

    #[Test]
    public function event_dates_are_cast_to_carbon()
    {
        $event = Event::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->start_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->end_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->updated_at);
    }

    #[Test]
    public function event_agenda_and_tags_are_cast_to_array()
    {
        $agenda = [
            ['time' => '09:00', 'topic' => 'Introduction'],
            ['time' => '10:00', 'topic' => 'Main Session']
        ];
        $tags = ['AI', 'Research', 'Workshop'];

        $event = Event::factory()->create([
            'agenda' => $agenda,
            'tags' => $tags
        ]);

        $this->assertIsArray($event->agenda);
        $this->assertIsArray($event->tags);
        $this->assertEquals($agenda, $event->agenda);
        $this->assertEquals($tags, $event->tags);
    }

    #[Test]
    public function event_moderation_dates_are_nullable_carbon()
    {
        $event = Event::factory()->create([
            'moderated_at' => now(),
            'banned_at' => now()
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->moderated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->banned_at);

        $eventWithoutDates = Event::factory()->create([
            'moderated_at' => null,
            'banned_at' => null
        ]);

        $this->assertNull($eventWithoutDates->moderated_at);
        $this->assertNull($eventWithoutDates->banned_at);
    }

    #[Test]
    public function event_fillable_attributes_work()
    {
        $host = User::factory()->create();
        $eventData = [
            'host_id' => $host->id,
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'timezone' => 'UTC',
            'location_type' => 'virtual',
            'virtual_link' => 'https://zoom.us/j/123456789',
            'capacity' => 50,
            'visibility' => 'public',
            'status' => 'published',
            'tags' => ['AI', 'Workshop']
        ];

        $event = Event::create($eventData);

        $this->assertEquals('Test Event', $event->title);
        $this->assertEquals('Test Description', $event->description);
        $this->assertEquals('virtual', $event->location_type);
        $this->assertEquals(50, $event->capacity);
        $this->assertEquals(['AI', 'Workshop'], $event->tags);
    }

    #[Test]
    public function event_belongs_to_moderated_by_user()
    {
        $moderator = User::factory()->create();
        $event = Event::factory()->create(['moderated_by' => $moderator->id]);

        $this->assertInstanceOf(User::class, $event->moderatedBy);
        $this->assertEquals($moderator->id, $event->moderatedBy->id);
    }

    #[Test]
    public function event_without_moderator_returns_null()
    {
        $event = Event::factory()->create(['moderated_by' => null]);

        $this->assertNull($event->moderatedBy);
    }
}
