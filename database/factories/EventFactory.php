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
        $startDate = now()->addDays(7);
        $endDate = clone $startDate;
        $endDate->modify('+3 hours');

        return [
            'uuid' => sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                mt_rand(0, 0xffffffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffffffffffff)
            ),
            'title' => 'Test Event',
            'description' => 'Test description for the event.',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timezone' => 'UTC',
            'location_type' => 'virtual',
            'location' => 'Test Address',
            'virtual_link' => 'https://example.com',
            'capacity' => 50,
            'poster' => null,
            'tags' => ['workshop', 'seminar'],
            'requirements' => 'Test requirements',
            'agenda' => null,
            'host_id' => User::factory(),
            'visibility' => 'public',
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
            'ban_reason' => 'Test ban reason',
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
            $pastStartDate = now()->subDays(7);
            $pastEndDate = clone $pastStartDate;
            $pastEndDate->modify('+3 hours');
            
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
