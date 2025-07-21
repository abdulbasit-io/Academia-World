<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventResource>
 */
class EventResourceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EventResource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filenames = [
            'presentation.pdf',
            'research_paper.docx',
            'conference_agenda.pdf',
            'workshop_recording.mp4',
            'keynote_slides.pptx',
            'abstract_collection.pdf',
            'participant_guide.pdf',
            'technical_specifications.txt',
            'conference_proceedings.pdf',
            'speaker_bios.pdf',
        ];
        
        $originalFilename = fake()->randomElement($filenames);
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = 'resource_' . fake()->randomNumber(6) . '_' . Str::uuid() . '.' . $fileExtension;
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp4' => 'video/mp4',
            'txt' => 'text/plain',
        ];
        
        return [
            'event_id' => Event::factory(),
            'uploaded_by' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional(0.7)->paragraph(),
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'file_path' => 'event-resources/' . $filename,
            'file_type' => $fileExtension,
            'mime_type' => $mimeTypes[$fileExtension] ?? 'application/octet-stream',
            'file_size' => fake()->numberBetween(1024, 52428800), // 1KB to 50MB
            'resource_type' => fake()->randomElement(['presentation', 'paper', 'recording', 'agenda', 'other']),
            'is_public' => fake()->boolean(60), // 60% chance of being public
            'is_downloadable' => fake()->boolean(80), // 80% chance of being downloadable
            'requires_registration' => fake()->boolean(40), // 40% chance of requiring registration
            'view_count' => fake()->numberBetween(0, 100),
            'download_count' => fake()->numberBetween(0, 50),
        ];
    }
    
    /**
     * Indicate that the resource is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
    
    /**
     * Indicate that the resource is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
    
    /**
     * Indicate that the resource is downloadable.
     */
    public function downloadable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_downloadable' => true,
        ]);
    }
    
    /**
     * Indicate that the resource is not downloadable.
     */
    public function notDownloadable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_downloadable' => false,
        ]);
    }
    
    /**
     * Indicate that the resource requires registration.
     */
    public function requiresRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_registration' => true,
        ]);
    }
    
    /**
     * Create a PDF presentation resource.
     */
    public function presentation(): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_type' => 'presentation',
            'original_filename' => 'presentation.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'title' => fake()->sentence(2) . ' Presentation',
        ]);
    }
    
    /**
     * Create a research paper resource.
     */
    public function paper(): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_type' => 'paper',
            'original_filename' => 'research_paper.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'title' => fake()->sentence(3) . ' Research Paper',
        ]);
    }
    
    /**
     * Create a recording resource.
     */
    public function recording(): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_type' => 'recording',
            'original_filename' => 'session_recording.mp4',
            'file_type' => 'mp4',
            'mime_type' => 'video/mp4',
            'title' => fake()->sentence(2) . ' Recording',
            'file_size' => fake()->numberBetween(104857600, 1073741824), // 100MB to 1GB for video
        ]);
    }
}
