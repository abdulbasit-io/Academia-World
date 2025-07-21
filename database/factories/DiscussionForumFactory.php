<?php

namespace Database\Factories;

use App\Models\DiscussionForum;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscussionForum>
 */
class DiscussionForumFactory extends Factory
{
    protected $model = DiscussionForum::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['general', 'q_and_a', 'networking', 'feedback', 'technical'];
        
        $titles = [
            'general' => [
                'General Discussion',
                'Welcome & Introductions',
                'Open Forum',
                'Community Chat',
            ],
            'q_and_a' => [
                'Q&A Session',
                'Ask the Experts',
                'Technical Questions',
                'Research Questions',
            ],
            'networking' => [
                'Networking Lounge',
                'Connect & Collaborate',
                'Professional Networking',
                'Find Collaborators',
            ],
            'feedback' => [
                'Event Feedback',
                'Suggestions & Ideas',
                'Share Your Thoughts',
                'Improvement Ideas',
            ],
            'technical' => [
                'Technical Discussion',
                'Implementation Details',
                'Best Practices',
                'Technical Support',
            ],
        ];

        $type = fake()->randomElement($types);
        $title = fake()->randomElement($titles[$type]);

        return [
            'event_id' => Event::factory(),
            'title' => $title,
            'description' => fake()->optional()->paragraph(),
            'type' => $type,
            'is_active' => true,
            'is_moderated' => fake()->boolean(30), // 30% chance of being moderated
            'created_by' => User::factory(),
            'post_count' => 0,
            'participant_count' => 0,
            'last_activity_at' => null,
        ];
    }

    /**
     * Indicate that the forum is moderated.
     */
    public function moderated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_moderated' => true,
        ]);
    }

    /**
     * Indicate that the forum is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create forum of specific type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
