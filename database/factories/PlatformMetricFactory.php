<?php

namespace Database\Factories;

use App\Models\PlatformMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformMetricFactory extends Factory
{
    protected $model = PlatformMetric::class;

    public function definition(): array
    {
        $metricNames = [
            'total_users',
            'total_events', 
            'daily_active_users',
            'monthly_active_users',
            'event_registrations',
            'forum_posts',
            'user_connections',
            'page_views',
            'session_duration',
            'bounce_rate'
        ];

        return [
            'id' => $this->faker->uuid(),
            'metric_name' => $this->faker->randomElement($metricNames),
            'metric_value' => $this->faker->numberBetween(1, 10000),
            'metric_date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'metadata' => [
                'calculated_at' => now(),
                'source' => 'automated',
            ],
            'created_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * Create metric for daily active users
     */
    public function dailyActiveUsers(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'metric_name' => 'daily_active_users',
                'metric_value' => $this->faker->numberBetween(50, 500),
            ];
        });
    }

    /**
     * Create metric for total users
     */
    public function totalUsers(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'metric_name' => 'total_users',
                'metric_value' => $this->faker->numberBetween(100, 10000),
            ];
        });
    }

    /**
     * Create metric for today
     */
    public function today(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'metric_date' => now()->format('Y-m-d'),
                'created_at' => now(),
            ];
        });
    }

    /**
     * Create metric for specific date
     */
    public function forDate(string $date): Factory
    {
        return $this->state(function (array $attributes) use ($date) {
            return [
                'metric_date' => $date,
            ];
        });
    }

    /**
     * Create growth metric
     */
    public function growth(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'metric_name' => $this->faker->randomElement(['user_growth', 'event_growth', 'engagement_growth']),
                'metric_value' => $this->faker->randomFloat(2, -10, 50), // Growth percentage
                'metadata' => [
                    'period' => 'monthly',
                    'comparison' => 'previous_period',
                ],
            ];
        });
    }
}
