<?php

use App\Models\User;
use App\Models\Event;
use App\Models\EventResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake the storage for testing
        Storage::fake('public');
    }

    #[Test]
    public function user_can_upload_avatar()
    {
        // Skip test if GD extension is not available
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create a fake image file
        $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $avatar
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Avatar updated successfully'
        ]);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        // Check that avatar is a complete URL (not just a path)
        $this->assertStringStartsWith('http', $user->avatar);
        
        // For local storage, verify file exists by extracting path from URL
        if (str_contains($user->avatar, '/storage/')) {
            $path = str_replace(config('app.url') . '/storage/', '', $user->avatar);
            $this->assertTrue(Storage::disk('public')->exists($path));
        }
    }

    #[Test]
    public function avatar_upload_requires_authentication()
    {
        $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $avatar
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function avatar_upload_validates_file_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Try to upload a PDF instead of an image
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $invalidFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    #[Test]
    public function avatar_upload_validates_file_size()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create a file larger than 2MB
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $largeFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    #[Test]
    public function avatar_upload_replaces_existing_avatar()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $user = User::factory()->create(['avatar' => 'avatars/old-avatar.jpg']);
        $this->actingAs($user, 'sanctum');

        // Create the old avatar file
        Storage::disk('public')->put('avatars/old-avatar.jpg', 'old content');

        $newAvatar = UploadedFile::fake()->image('new-avatar.jpg', 300, 300);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $newAvatar
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotEquals('avatars/old-avatar.jpg', $user->avatar);
        $this->assertFalse(Storage::disk('public')->exists('avatars/old-avatar.jpg'));
        
        // Check that new avatar exists by extracting path from URL
        if (str_contains($user->avatar, '/storage/')) {
            $path = str_replace(config('app.url') . '/storage/', '', $user->avatar);
            $this->assertTrue(Storage::disk('public')->exists($path));
        }
    }

    #[Test]
    public function user_can_delete_avatar()
    {
        // Create user with URL-format avatar (new format)
        $avatarUrl = config('app.url') . '/storage/avatars/test-avatar.jpg';
        $user = User::factory()->create(['avatar' => $avatarUrl]);
        $this->actingAs($user, 'sanctum');

        // Create the avatar file
        Storage::disk('public')->put('avatars/test-avatar.jpg', 'test content');

        $response = $this->deleteJson('/api/v1/profile/avatar');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Avatar deleted successfully'
        ]);

        $user->refresh();
        $this->assertNull($user->avatar);
        $this->assertFalse(Storage::disk('public')->exists('avatars/test-avatar.jpg'));
    }

    #[Test]
    public function event_host_can_upload_poster()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $poster = UploadedFile::fake()->image('poster.jpg', 800, 600);

        $eventData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_date' => now()->addWeek()->toISOString(),
            'end_date' => now()->addWeek()->addHours(2)->toISOString(),
            'location_type' => 'physical',
            'location' => 'Test Location',
            'capacity' => 100,
            'visibility' => 'public',
            'poster' => $poster
        ];

        $response = $this->postJson('/api/v1/events', $eventData);

        $response->assertStatus(201);
        
        $event = Event::first();
        $this->assertNotNull($event->poster);
        
        // Check that poster exists by extracting path from URL
        if (str_contains($event->poster, '/storage/')) {
            $path = str_replace(config('app.url') . '/storage/', '', $event->poster);
            $this->assertTrue(Storage::disk('public')->exists($path));
        }
    }

    #[Test]
    public function event_poster_upload_validates_file_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        $eventData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_date' => now()->addWeek()->toISOString(),
            'end_date' => now()->addWeek()->addHours(2)->toISOString(),
            'location_type' => 'physical',
            'location' => 'Test Location',
            'capacity' => 100,
            'poster' => $invalidFile
        ];

        $response = $this->postJson('/api/v1/events', $eventData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['poster']);
    }

    #[Test]
    public function event_host_can_upload_resource()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $user->id]);
        $this->actingAs($user, 'sanctum');

        $file = UploadedFile::fake()->create('presentation.pdf', 1000, 'application/pdf');

        $resourceData = [
            'file' => $file,
            'title' => 'Test Resource',
            'description' => 'Test Description',
            'resource_type' => 'presentation',
            'is_public' => true,
            'is_downloadable' => true,
            'requires_registration' => false
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/resources", $resourceData);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Resource uploaded successfully'
        ]);

        $resource = EventResource::first();
        $this->assertNotNull($resource);
        $this->assertEquals('Test Resource', $resource->title);
        
        // Check that file exists by extracting path from URL
        if (str_contains($resource->file_path, '/storage/')) {
            $path = str_replace(config('app.url') . '/storage/', '', $resource->file_path);
            $this->assertTrue(Storage::disk('public')->exists($path));
        }
    }

    #[Test]
    public function resource_upload_validates_file_size()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $user->id]);
        $this->actingAs($user, 'sanctum');

        // Create a file larger than 50MB
        $largeFile = UploadedFile::fake()->create('large-file.pdf', 52000); // 52MB

        $resourceData = [
            'file' => $largeFile,
            'title' => 'Large File',
            'resource_type' => 'presentation'
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/resources", $resourceData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function resource_upload_validates_file_type()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $user->id]);
        $this->actingAs($user, 'sanctum');

        // Create an executable file (not allowed)
        $invalidFile = UploadedFile::fake()->create('malicious.exe', 1000, 'application/x-msdownload');

        $resourceData = [
            'file' => $invalidFile,
            'title' => 'Invalid File',
            'resource_type' => 'other'
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/resources", $resourceData);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'File type not allowed'
        ]);
    }

    #[Test]
    public function only_event_host_or_admin_can_upload_resources()
    {
        $host = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);
        
        $this->actingAs($otherUser, 'sanctum');

        $file = UploadedFile::fake()->create('presentation.pdf', 1000, 'application/pdf');

        $resourceData = [
            'file' => $file,
            'title' => 'Unauthorized Upload',
            'resource_type' => 'presentation'
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/resources", $resourceData);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You are not authorized to upload resources for this event'
        ]);
    }

    #[Test]
    public function admin_can_upload_resources_to_any_event()
    {
        $host = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create(['host_id' => $host->id]);
        
        $this->actingAs($admin, 'sanctum');

        $file = UploadedFile::fake()->create('admin-resource.pdf', 1000, 'application/pdf');

        $resourceData = [
            'file' => $file,
            'title' => 'Admin Resource',
            'resource_type' => 'presentation'
        ];

        $response = $this->postJson("/api/v1/events/{$event->uuid}/resources", $resourceData);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Resource uploaded successfully'
        ]);
    }

    #[Test]
    public function uploaded_files_have_correct_urls()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $avatar
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'avatar_url'
        ]);

        $avatarUrl = $response->json('avatar_url');
        $this->assertStringStartsWith(config('app.url') . '/storage/', $avatarUrl);
    }

    #[Test]
    public function file_upload_handles_missing_file_gracefully()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/profile/avatar', [
            // No file provided
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    #[Test]
    public function resource_download_requires_proper_permissions()
    {
        $host = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create(['host_id' => $host->id]);
        
        // Create a private resource
        $resource = EventResource::factory()->create([
            'event_id' => $event->id,
            'uploaded_by' => $host->id,
            'is_public' => false,
            'requires_registration' => true,
            'file_path' => 'event-resources/test-file.pdf'
        ]);

        // Create the file
        Storage::disk('public')->put('event-resources/test-file.pdf', 'test content');

        $this->actingAs($otherUser, 'sanctum');

        $response = $this->getJson("/api/v1/resources/{$resource->uuid}/download");

        $response->assertStatus(403);
    }
}
