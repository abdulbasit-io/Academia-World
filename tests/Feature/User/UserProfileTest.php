<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class UserProfileTest extends TestCase
{
    #[Test]
    public function authenticated_user_can_get_profile()
    {
        $user = $this->authenticateUser([
            'bio' => 'Professor of Computer Science',
            'website' => 'https://johndoe.com',
            'social_links' => [
                'twitter' => 'https://twitter.com/johndoe',
                'linkedin' => 'https://linkedin.com/in/johndoe'
            ]
        ]);

        $response = $this->getJson('/api/v1/profile', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'first_name',
                'last_name',
                'email',
                'institution',
                'department',
                'position',
                'bio',
                'website',
                'social_links',
                'avatar',
                'account_status',
                'is_admin',
                'created_at'
            ]
        ]);

        $response->assertJson([
            'data' => [
                'bio' => 'Professor of Computer Science',
                'website' => 'https://johndoe.com'
            ]
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_profile()
    {
        $response = $this->getJson('/api/v1/profile', $this->getApiHeaders());

        $response->assertStatus(401);
    }

    #[Test]
    public function user_can_update_profile()
    {
        $user = $this->authenticateUser();

        $updateData = [
            'bio' => 'Updated bio: Senior Researcher in AI',
            'website' => 'https://updated-website.com',
            'social_links' => [
                'twitter' => 'https://twitter.com/updated',
                'linkedin' => 'https://linkedin.com/in/updated',
                'github' => 'https://github.com/updated'
            ]
        ];

        $response = $this->putJson('/api/v1/profile', $updateData, $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile updated successfully'
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'Updated bio: Senior Researcher in AI',
            'website' => 'https://updated-website.com'
        ]);

        $user->refresh();
        $this->assertEquals([
            'twitter' => 'https://twitter.com/updated',
            'linkedin' => 'https://linkedin.com/in/updated',
            'github' => 'https://github.com/updated'
        ], $user->social_links);
    }

    #[Test]
    public function profile_update_validates_website_url()
    {
        $user = $this->authenticateUser();

        $updateData = [
            'website' => 'invalid-url',
            'bio' => str_repeat('a', 1001) // Too long
        ];

        $response = $this->putJson('/api/v1/profile', $updateData, $this->getApiHeaders());

        $this->assertValidationError($response, ['website', 'bio']);
    }

    #[Test]
    public function profile_update_validates_social_links()
    {
        $user = $this->authenticateUser();

        $updateData = [
            'social_links' => [
                'twitter' => 'invalid-url',
                'linkedin' => 'also-invalid',
                'github' => 'still-invalid'
            ]
        ];

        $response = $this->putJson('/api/v1/profile', $updateData, $this->getApiHeaders());

        $this->assertValidationError($response, [
            'social_links.twitter',
            'social_links.linkedin', 
            'social_links.github'
        ]);
    }

    #[Test]
    public function user_can_upload_avatar()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }
        
        Storage::fake('public');
        $user = $this->authenticateUser();

        $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $avatar
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Avatar updated successfully'
        ]);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertTrue(Storage::disk('public')->exists($user->avatar));
    }

    #[Test]
    public function avatar_upload_validates_file_type()
    {
        $user = $this->authenticateUser();

        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $invalidFile
        ], ['Accept' => 'application/json']);

        $this->assertValidationError($response, ['avatar']);
    }

    #[Test]
    public function avatar_upload_validates_file_size()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }
        
        $user = $this->authenticateUser();

        // Create a file larger than 2MB
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $largeFile
        ], ['Accept' => 'application/json']);

        $this->assertValidationError($response, ['avatar']);
    }

    #[Test]
    public function user_can_delete_avatar()
    {
        Storage::fake('public');
        $user = $this->authenticateUser(['avatar' => 'avatars/test-avatar.jpg']);
        
        // Create the file so it exists
        Storage::disk('public')->put('avatars/test-avatar.jpg', 'fake-content');

        $response = $this->deleteJson('/api/v1/profile/avatar', [], $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Avatar deleted successfully'
        ]);

        $user->refresh();
        $this->assertNull($user->avatar);
        $this->assertFalse(Storage::disk('public')->exists('avatars/test-avatar.jpg'));
    }

    #[Test]
    public function user_can_get_statistics()
    {
        $user = $this->authenticateUser();

        // Create hosted events
        Event::factory()->count(3)->create([
            'host_id' => $user->id,
            'status' => 'published',
            'start_date' => now()->addDays(10) // Upcoming
        ]);

        Event::factory()->count(2)->create([
            'host_id' => $user->id,
            'status' => 'completed',
            'start_date' => now()->subDays(10) // Completed
        ]);

        // Create registered events
        $events = Event::factory()->count(4)->create([
            'status' => 'published',
            'start_date' => now()->addDays(5)
        ]);

        foreach ($events as $event) {
            $event->registrations()->attach($user->id, [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        // Add some attendees to hosted events
        $hostedEvent = Event::where('host_id', $user->id)->first();
        $attendees = User::factory()->count(10)->create();
        foreach ($attendees as $attendee) {
            $hostedEvent->registrations()->attach($attendee->id, [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }

        $response = $this->getJson('/api/v1/profile/stats', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'hosted_events_total',
                'hosted_events_upcoming',
                'hosted_events_completed',
                'registered_events_total',
                'registered_events_upcoming',
                'total_attendees_hosted',
                'profile_completion'
            ]
        ]);

        $responseData = $response->json('data');
        $this->assertEquals(5, $responseData['hosted_events_total']);
        $this->assertEquals(3, $responseData['hosted_events_upcoming']);
        $this->assertEquals(2, $responseData['hosted_events_completed']);
        $this->assertEquals(4, $responseData['registered_events_total']);
        $this->assertEquals(4, $responseData['registered_events_upcoming']);
        $this->assertEquals(10, $responseData['total_attendees_hosted']);
    }

    #[Test]
    public function profile_completion_calculation_works()
    {
        // User with minimal profile
        $minimalUser = $this->authenticateUser([
            'bio' => null,
            'website' => null,
            'avatar' => null,
            'social_links' => null
        ]);

        $response = $this->getJson('/api/v1/profile/stats', $this->getApiHeaders());
        $response->assertStatus(200);
        
        $completion = $response->json('data.profile_completion');
        $this->assertLessThan(100, $completion);

        // User with complete profile
        $completeUser = User::factory()->create([
            'account_status' => 'active',
            'email_verified_at' => now(),
            'bio' => 'Complete bio',
            'website' => 'https://example.com',
            'avatar' => 'avatars/avatar.jpg',
            'social_links' => [
                'twitter' => 'https://twitter.com/user',
                'linkedin' => 'https://linkedin.com/in/user'
            ]
        ]);

        Sanctum::actingAs($completeUser);
        
        $response = $this->getJson('/api/v1/profile/stats', $this->getApiHeaders());
        $response->assertStatus(200);
        
        $completion = $response->json('data.profile_completion');
        $this->assertEquals(100, $completion);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_profile_endpoints()
    {
        $endpoints = [
            ['GET', '/api/v1/profile'],
            ['PUT', '/api/v1/profile'],
            ['POST', '/api/v1/profile/avatar'],
            ['DELETE', '/api/v1/profile/avatar'],
            ['GET', '/api/v1/profile/stats']
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint, [], $this->getApiHeaders());
            $response->assertStatus(401);
        }
    }

    #[Test]
    public function profile_shows_correct_user_data()
    {
        $user = $this->authenticateUser([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'institution' => 'MIT',
            'department' => 'Computer Science',
            'position' => 'Professor',
            'bio' => 'AI Researcher'
        ]);

        $response = $this->getJson('/api/v1/profile', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'institution' => 'MIT',
                'department' => 'Computer Science',
                'position' => 'Professor',
                'bio' => 'AI Researcher',
                'is_admin' => false
            ]
        ]);
    }

    #[Test]
    public function admin_user_shows_admin_status()
    {
        $admin = $this->authenticateAdmin();

        $response = $this->getJson('/api/v1/profile', $this->getApiHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'is_admin' => true
            ]
        ]);
    }
}
