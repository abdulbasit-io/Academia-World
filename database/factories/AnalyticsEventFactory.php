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
            'uuid' => sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                mt_rand(0, 0xffffffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffffffffffff)
            ),
            'event_type' => 'user_action',
            'action' => 'page_view',
            'entity_type' => 'event',
            'entity_id' => 1,
            'user_id' => User::factory(),
            'metadata' => [
                'page' => 123,
                'source' => 'direct',
            ],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'session_id' => sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                mt_rand(0, 0xffffffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffffffffffff)
            ),
            'occurred_at' => now(),
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
                'occurred_at' => now(),
            ];
        });
    }
}
