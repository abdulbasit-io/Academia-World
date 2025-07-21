<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'event_type' => $this->faker->randomElement(['user_action', 'engagement_metric', 'system_event']),
            'action' => $this->faker->randomElement(['page_view', 'click', 'event_view', 'event_register', 'forum_post', 'user_connect']),
            'entity_type' => $this->faker->randomElement(['event', 'user', 'forum', 'post']),
            'entity_id' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'metadata' => [
                'page' => $this->faker->randomNumber(),
                'source' => $this->faker->randomElement(['organic', 'direct', 'social']),
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'session_id' => $this->faker->uuid(),
            'occurred_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Create analytics event for page view
     */
    public function pageView(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'user_action',
                'action' => 'page_view',
            ];
        });
    }

    /**
     * Create analytics event for event registration
     */
    public function eventRegistration(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'engagement_metric',
                'action' => 'event_register',
                'entity_type' => 'event',
            ];
        });
    }

    /**
     * Create analytics event for recent activity
     */
    public function recent(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'occurred_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            ];
        });
    }
}
