<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class UserModelTest extends TestCase
{
    #[Test]
    public function user_has_uuid_generated_on_creation()
    {
        $user = User::factory()->create();
        
        $this->assertNotNull($user->uuid);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($user->uuid));
    }

    #[Test]
    public function user_full_name_accessor_works()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        $this->assertEquals('John Doe', $user->full_name);
    }

    #[Test]
    public function user_is_admin_method_works()
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $adminUser = User::factory()->create(['is_admin' => true]);

        $this->assertFalse($regularUser->isAdmin());
        $this->assertTrue($adminUser->isAdmin());
    }

    #[Test]
    public function user_is_active_method_works()
    {
        $activeUser = User::factory()->create([
            'account_status' => 'active',
            'email_verified_at' => now()
        ]);
        
        $pendingUser = User::factory()->create([
            'account_status' => 'pending',
            'email_verified_at' => null
        ]);

        $this->assertTrue($activeUser->isActive());
        $this->assertFalse($pendingUser->isActive());
    }

    #[Test]
    public function user_mark_email_as_verified_works()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'account_status' => 'pending'
        ]);

        $this->assertNull($user->email_verified_at);
        $this->assertEquals('pending', $user->account_status);

        $user->markEmailAsVerified();

        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('active', $user->account_status);
    }

    #[Test]
    public function user_has_hosted_events_relationship()
    {
        $user = User::factory()->create();
        $events = Event::factory()->count(3)->create(['host_id' => $user->id]);

        $this->assertCount(3, $user->hostedEvents);
        $this->assertInstanceOf(Event::class, $user->hostedEvents->first());
    }

    #[Test]
    public function user_has_registered_events_relationship()
    {
        $user = User::factory()->create();
        $events = Event::factory()->count(3)->create();

        foreach ($events as $event) {
            $event->registrations()->attach($user->id, [
                'uuid' => Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $this->assertCount(3, $user->registeredEvents);
        $this->assertInstanceOf(Event::class, $user->registeredEvents->first());
    }

    #[Test]
    public function user_route_key_is_uuid()
    {
        $user = User::factory()->create();

        $this->assertEquals('uuid', $user->getRouteKeyName());
    }

    #[Test]
    public function user_find_by_uuid_works()
    {
        $user = User::factory()->create();

        $foundUser = User::findByUuid($user->uuid);

        $this->assertEquals($user->id, $foundUser->id);
    }

    #[Test]
    public function user_find_by_uuid_returns_null_for_invalid_uuid()
    {
        $foundUser = User::findByUuid('invalid-uuid');

        $this->assertNull($foundUser);
    }

    #[Test]
    public function user_password_is_hashed()
    {
        $user = User::factory()->create(['password' => 'plain-password']);

        $this->assertTrue(Hash::check('plain-password', $user->password));
        $this->assertNotEquals('plain-password', $user->password);
    }

    #[Test]
    public function user_social_links_are_cast_to_array()
    {
        $socialLinks = [
            'twitter' => 'https://twitter.com/user',
            'linkedin' => 'https://linkedin.com/in/user'
        ];

        $user = User::factory()->create(['social_links' => $socialLinks]);

        $this->assertIsArray($user->social_links);
        $this->assertEquals($socialLinks, $user->social_links);
    }

    #[Test]
    public function user_hidden_attributes_are_not_serialized()
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    #[Test]
    public function user_fillable_attributes_work()
    {
        $userData = [
            'name' => 'Jane Smith',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@test.com',
            'password' => Hash::make('password'),
            'institution' => 'MIT',
            'department' => 'CS',
            'position' => 'Professor',
            'bio' => 'AI Researcher',
            'website' => 'https://jane.com',
            'uuid' => Str::uuid()->toString(),
        ];

        $user = User::create($userData);

        $this->assertEquals('Jane', $user->first_name);
        $this->assertEquals('Smith', $user->last_name);
        $this->assertEquals('jane@test.com', $user->email);
        $this->assertEquals('MIT', $user->institution);
    }

    #[Test]
    public function user_created_at_and_updated_at_are_carbon_instances()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->updated_at);
    }

    #[Test]
    public function user_email_verified_at_is_carbon_instance()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    #[Test]
    public function user_profile_completion_calculation()
    {
        // Test minimum profile
        $minUser = User::factory()->create([
            'bio' => null,
            'website' => null,
            'avatar' => null,
            'social_links' => null
        ]);

        $this->assertLessThan(100, $minUser->calculateProfileCompletion());

        // Test complete profile
        $completeUser = User::factory()->create([
            'bio' => 'Complete bio',
            'institution' => 'MIT',
            'department' => 'Computer Science',
            'position' => 'Professor',
            'avatar' => 'avatar.jpg',
        ]);

        $this->assertEquals(100, $completeUser->calculateProfileCompletion());
    }

    #[Test]
    public function user_can_check_if_can_edit_event()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $userEvent = Event::factory()->create(['host_id' => $user->id]);
        $otherEvent = Event::factory()->create(['host_id' => $otherUser->id]);

        $this->assertTrue($userEvent->canBeEditedBy($user));
        $this->assertFalse($otherEvent->canBeEditedBy($user));
    }

    #[Test]
    public function admin_can_moderate_any_event()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create();

        $this->assertTrue($event->canBeModeratedBy($admin));
        $this->assertFalse($event->canBeModeratedBy($regularUser));
    }
}
