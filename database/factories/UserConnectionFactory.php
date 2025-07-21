<?php

namespace Database\Factories;

use App\Models\UserConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserConnection>
 */
class UserConnectionFactory extends Factory
{
    protected $model = UserConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $messages = [
            'Would love to connect and collaborate!',
            'Hello! I\'d like to add you to my professional network.',
            'Great to meet you at the conference. Let\'s stay connected!',
            'I saw your presentation and would love to discuss more.',
            'Looking forward to connecting with fellow researchers.',
            null, // Some connections might not have messages
        ];

        return [
            'requester_id' => User::factory(),
            'addressee_id' => User::factory(),
            'status' => 'pending',
            'message' => fake()->randomElement($messages),
            'responded_at' => null,
        ];
    }

    /**
     * Indicate that the connection is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'responded_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the connection is declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'declined',
            'responded_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the connection is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'responded_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Create connection with specific message.
     */
    public function withMessage(string $message): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => $message,
        ]);
    }
}
