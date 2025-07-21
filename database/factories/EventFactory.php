<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $endDate = clone $startDate;
        $endDate->modify('+' . $this->faker->numberBetween(1, 6) . ' hours');

        return [
            'uuid' => Str::uuid()->toString(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraphs(3, true),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timezone' => 'UTC',
            'location_type' => $this->faker->randomElement(['physical', 'virtual', 'hybrid']),
            'location' => $this->faker->address(),
            'virtual_link' => $this->faker->optional()->url(),
            'capacity' => $this->faker->numberBetween(10, 100),
            'poster' => $this->faker->optional()->imageUrl(),
            'tags' => $this->faker->randomElements(['conference', 'workshop', 'seminar', 'networking', 'research'], $this->faker->numberBetween(1, 3)),
            'requirements' => $this->faker->optional()->sentence(),
            'agenda' => $this->faker->optional()->randomElement([
                null,
                [
                    ['time' => '09:00', 'activity' => 'Registration'],
                    ['time' => '10:00', 'activity' => 'Opening Keynote'],
                    ['time' => '11:30', 'activity' => 'Panel Discussion'],
                ]
            ]),
            'host_id' => User::factory(),
            'visibility' => $this->faker->randomElement(['public', 'private']),
            'status' => 'published',
        ];
    }

    /**
     * Indicate that the event is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the event is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
        ]);
    }

    /**
     * Indicate that the event is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the event is banned.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
            'banned_at' => now(),
            'ban_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the event is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the event is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Indicate that the event has passed.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes) {
            $pastStartDate = $this->faker->dateTimeBetween('-3 months', '-1 week');
            $pastEndDate = clone $pastStartDate;
            $pastEndDate->modify('+' . $this->faker->numberBetween(1, 6) . ' hours');
            
            return [
                'start_date' => $pastStartDate,
                'end_date' => $pastEndDate,
                'status' => 'completed',
            ];
        });
    }

    /**
     * Indicate that the event is full (no available spots).
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => 0,
        ]);
    }
}
