<?php

namespace Database\Factories;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminLogFactory extends Factory
{
    protected $model = AdminLog::class;

    public function definition(): array
    {
        $actions = ['user_ban', 'user_unban', 'user_promote', 'user_demote', 'content_delete', 'admin_create'];
        $targetTypes = ['user', 'post', 'event', 'forum'];
        $severities = ['info', 'warning', 'error', 'critical'];

        return [
            'id' => $this->faker->uuid(),
            'admin_id' => User::factory()->state(['is_admin' => true]),
            'action' => $this->faker->randomElement($actions),
            'target_type' => $this->faker->randomElement($targetTypes),
            'target_id' => $this->faker->randomNumber(),
            'description' => $this->faker->sentence(),
            'changes' => [
                'before' => ['status' => 'active'],
                'after' => ['status' => 'inactive'],
            ],
            'metadata' => [
                'reason' => $this->faker->sentence(),
                'ip_address' => $this->faker->ipv4(),
            ],
            'ip_address' => $this->faker->ipv4(),
            'severity' => $this->faker->randomElement($severities),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Create log for user ban action
     */
    public function userBan(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'user_ban',
                'target_type' => 'user',
                'severity' => 'warning',
                'description' => 'Banned user for policy violation',
            ];
        });
    }

    /**
     * Create log for content deletion
     */
    public function contentDelete(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'content_delete',
                'target_type' => 'post',
                'severity' => 'warning',
                'description' => 'Deleted inappropriate content',
            ];
        });
    }

    /**
     * Create log for user promotion
     */
    public function userPromotion(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'user_promote',
                'target_type' => 'user',
                'severity' => 'critical',
                'description' => 'Promoted user to admin',
            ];
        });
    }

    /**
     * Create recent log entry
     */
    public function recent(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }
}
