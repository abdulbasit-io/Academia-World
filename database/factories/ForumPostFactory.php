<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\DiscussionForum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contents = [
            'Great presentation! Looking forward to the discussion.',
            'Can you share more details about the methodology?',
            'This is exactly what I was looking for. Thank you!',
            'Has anyone tried implementing this approach?',
            'I have a question about the scalability of this solution.',
            'Very insightful research. How did you handle the data collection?',
            'Would love to collaborate on similar projects.',
            'The results are impressive. What are the next steps?',
            'This reminds me of a project I worked on last year.',
            'Excellent work! Could you recommend some resources for further reading?',
        ];

        return [
            'forum_id' => DiscussionForum::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => fake()->randomElement($contents),
            'is_pinned' => false,
            'is_solution' => false,
            'is_moderated' => false,
            'likes_count' => fake()->numberBetween(0, 15),
            'replies_count' => 0,
            'edited_at' => null,
            'edited_by' => null,
        ];
    }

    /**
     * Indicate that the post is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Indicate that the post is a solution.
     */
    public function solution(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_solution' => true,
        ]);
    }

    /**
     * Indicate that the post is a reply to another post.
     */
    public function reply(ForumPost $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'forum_id' => $parent->forum_id,
        ]);
    }

    /**
     * Create post with specific content.
     */
    public function withContent(string $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }

    /**
     * Create post with many likes.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'likes_count' => fake()->numberBetween(20, 100),
        ]);
    }
}
