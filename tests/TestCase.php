<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Refresh database for each test
        $this->artisan('migrate:fresh');
        
        // Seed basic data if needed
        $this->seed();
    }

    /**
     * Create and authenticate a regular user
     */
    protected function authenticateUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));

        Sanctum::actingAs($user);
        
        return $user;
    }

    /**
     * Create and authenticate an admin user
     */
    protected function authenticateAdmin(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge([
            'account_status' => 'active',
            'email_verified_at' => now(),
            'is_admin' => true,
        ], $attributes));

        Sanctum::actingAs($admin);
        
        return $admin;
    }

    /**
     * Create a pending user (not verified)
     */
    protected function createPendingUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'account_status' => 'pending',
            'email_verified_at' => null,
        ], $attributes));
    }

    /**
     * Get standard API headers
     */
    protected function getApiHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Assert API response structure
     */
    protected function assertApiResponse($response, int $status = 200, array $structure = []): void
    {
        $response->assertStatus($status);
        $response->assertJson(['message' => true]);
        
        if (!empty($structure)) {
            $response->assertJsonStructure($structure);
        }
    }

    /**
     * Assert validation error response
     */
    protected function assertValidationError($response, array $fields = []): void
    {
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors'
        ]);
        
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $response->assertJsonValidationErrors($field);
            }
        }
    }
}
