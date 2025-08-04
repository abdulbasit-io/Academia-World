<?php

use App\Models\User;
use App\Models\Event;
use App\Models\EventResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    
    $this->user = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->otherUser = User::factory()->create();
    
    $this->event = Event::factory()->create([
        'host_id' => $this->user->id,
        'title' => 'Test Conference',
        'description' => 'A test conference for academic purposes',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(7)->addHours(4),
        'location_type' => 'physical',
        'location' => 'Conference Hall',
        'capacity' => 100,
    ]);
});

it('can list event resources publicly', function () {
    // Create public and private resources
    $publicResource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
    ]);
    
    $privateResource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => false,
    ]);
    
    $response = $this->getJson("/api/v1/events/{$this->event->uuid}/resources");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'description',
                    'original_filename',
                    'file_type',
                    'file_size',
                    'resource_type',
                    'is_public',
                    'is_downloadable',
                    'created_at',
                ]
            ]
        ]);
    
    $responseData = $response->json('data');
    
    // Should only show public resource for unauthenticated request
    expect($responseData)->toHaveCount(1);
    expect($responseData[0]['uuid'])->toBe($publicResource->uuid);
});

it('can list event resources with authentication', function () {
    // Create resources
    $publicResource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
    ]);
    
    $privateResource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => false,
        'requires_registration' => false,
    ]);
    
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/events/{$this->event->uuid}/resources");
    
    $response->assertStatus(200);
    
    $responseData = $response->json('data');
    
    // Should show both resources for authenticated user
    expect($responseData)->toHaveCount(2);
});

it('can upload a resource as event host', function () {
    $file = UploadedFile::fake()->create('presentation.pdf', 1024, 'application/pdf');
    
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/{$this->event->uuid}/resources", [
            'file' => $file,
            'title' => 'Conference Presentation',
            'description' => 'Main presentation for the conference',
            'resource_type' => 'presentation',
            'is_public' => true,
            'is_downloadable' => true,
            'requires_registration' => false,
        ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'title',
                'description',
                'original_filename',
                'file_type',
                'file_size',
                'resource_type',
                'is_public',
                'is_downloadable',
                'requires_registration',
                'created_at',
            ]
        ]);
    
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('Conference Presentation');
    expect($responseData['resource_type'])->toBe('presentation');
    expect($responseData['is_public'])->toBe(true);
    
    // Check database
    $this->assertDatabaseHas('event_resources', [
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'title' => 'Conference Presentation',
        'resource_type' => 'presentation',
    ]);
    
    // Check file was stored
    $resource = EventResource::where('title', 'Conference Presentation')->first();
    
    // Check that file_path is a complete URL (not just a path)
    expect($resource->file_path)->toStartWith('http');
    
    // For local storage, verify file exists by extracting path from URL
    if (str_contains($resource->file_path, '/storage/')) {
        $path = str_replace(config('app.url') . '/storage/', '', $resource->file_path);
        expect(Storage::disk('public')->exists($path))->toBeTrue();
    }
});

it('prevents non-host from uploading resources', function () {
    $file = UploadedFile::fake()->create('presentation.pdf', 1024, 'application/pdf');
    
    $response = $this->actingAs($this->otherUser)
        ->postJson("/api/v1/events/{$this->event->uuid}/resources", [
            'file' => $file,
            'title' => 'Unauthorized Upload',
            'resource_type' => 'presentation',
        ]);
    
    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You are not authorized to upload resources for this event'
        ]);
});

it('validates file upload requirements', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/{$this->event->uuid}/resources", [
            'title' => 'No File Upload',
            'resource_type' => 'presentation',
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('validates file type restrictions', function () {
    // Try uploading an executable file (not allowed)
    $file = UploadedFile::fake()->create('malware.exe', 1024, 'application/x-msdownload');
    
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/{$this->event->uuid}/resources", [
            'file' => $file,
            'title' => 'Malicious File',
            'resource_type' => 'other',
        ]);
    
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'File type not allowed'
        ]);
});

it('can view resource details with access permission', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
    ]);
    
    $response = $this->getJson("/api/v1/resources/{$resource->uuid}");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'uuid',
                'title',
                'description',
                'original_filename',
                'file_type',
                'file_size',
                'resource_type',
                'is_public',
                'is_downloadable',
                'download_count',
                'view_count',
                'event',
                'uploaded_by',
                'created_at',
                'updated_at',
            ]
        ]);
    
    expect($response->json('data.uuid'))->toBe($resource->uuid);
});

it('prevents access to private resources without permission', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => false,
        'requires_registration' => true,
    ]);
    
    $response = $this->actingAs($this->otherUser)
        ->getJson("/api/v1/resources/{$resource->uuid}");
    
    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You do not have permission to access this resource'
        ]);
});

it('can update resource metadata as host', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'title' => 'Original Title',
        'is_public' => false,
    ]);
    
    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/resources/{$resource->uuid}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'is_public' => true,
            'is_downloadable' => false,
        ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Resource updated successfully',
            'data' => [
                'title' => 'Updated Title',
                'description' => 'Updated description',
                'is_public' => true,
                'is_downloadable' => false,
            ]
        ]);
    
    $resource->refresh();
    expect($resource->title)->toBe('Updated Title');
    expect($resource->is_public)->toBe(true);
});

it('prevents non-host from updating resources', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
    ]);
    
    $response = $this->actingAs($this->otherUser)
        ->putJson("/api/v1/resources/{$resource->uuid}", [
            'title' => 'Unauthorized Update',
        ]);
    
    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You are not authorized to update this resource'
        ]);
});

it('can delete resource as host', function () {
    Storage::fake('public');
    
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'file_path' => 'event-resources/test-file.pdf',
    ]);
    
    // Create a fake file
    Storage::disk('public')->put($resource->file_path, 'fake file content');
    
    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/resources/{$resource->uuid}");
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Resource deleted successfully'
        ]);
    
    // Check file is deleted from storage
    expect(Storage::disk('public')->exists($resource->file_path))->toBeFalse();
});

it('can download public resource', function () {
    Storage::fake('public');
    
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
        'is_downloadable' => true,
        'file_path' => 'event-resources/test-download.pdf',
        'original_filename' => 'presentation.pdf',
        'download_count' => 0,
    ]);
    
    // Create a fake file
    Storage::disk('public')->put($resource->file_path, 'PDF content here');
    
    $response = $this->getJson("/api/v1/resources/{$resource->uuid}/download");
    
    $response->assertStatus(200);
    
    // Check download count incremented
    $resource->refresh();
    expect($resource->download_count)->toBe(1);
});

it('prevents download of non-downloadable resource', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
        'is_downloadable' => false,
    ]);
    
    $response = $this->getJson("/api/v1/resources/{$resource->uuid}/download");
    
    $response->assertStatus(403)
        ->assertJson([
            'message' => 'This resource is not available for download'
        ]);
});

it('allows admin to manage any resource', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'title' => 'Host Resource',
    ]);
    
    // Admin can update
    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/resources/{$resource->uuid}", [
            'title' => 'Admin Updated Title',
        ]);
    
    $response->assertStatus(200);
    
    // Admin can delete
    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/resources/{$resource->uuid}");
    
    $response->assertStatus(200);
});

it('can filter resources by type', function () {
    EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'resource_type' => 'presentation',
        'is_public' => true,
    ]);
    
    EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'resource_type' => 'paper',
        'is_public' => true,
    ]);
    
    $response = $this->getJson("/api/v1/events/{$this->event->uuid}/resources?type=presentation");
    
    $response->assertStatus(200);
    
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(1);
    expect($responseData[0]['resource_type'])->toBe('presentation');
});

it('tracks resource analytics correctly', function () {
    $resource = EventResource::factory()->create([
        'event_id' => $this->event->id,
        'uploaded_by' => $this->user->id,
        'is_public' => true,
        'view_count' => 0,
        'download_count' => 0,
    ]);
    
    // View resource (should increment view count)
    $this->actingAs($this->user)
        ->getJson("/api/v1/resources/{$resource->uuid}");
    
    $resource->refresh();
    expect($resource->view_count)->toBe(1);
    
    // View again (should increment again)
    $this->actingAs($this->user)
        ->getJson("/api/v1/resources/{$resource->uuid}");
    
    $resource->refresh();
    expect($resource->view_count)->toBe(2);
});
