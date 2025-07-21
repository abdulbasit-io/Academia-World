<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        
        return [
            'name' => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'uuid' => Str::uuid()->toString(),
            'bio' => $this->faker->paragraph(),
            'institution' => $this->faker->company(),
            'department' => $this->faker->jobTitle(),
            'position' => $this->faker->jobTitle(),
            'website' => $this->faker->optional()->url(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'social_links' => $this->faker->optional()->randomElement([
                null,
                [
                    'twitter' => '@' . $this->faker->userName(),
                    'linkedin' => $this->faker->url(),
                ]
            ]),
            'account_status' => 'pending',
            'preferences' => $this->faker->optional()->randomElement([
                null,
                ['notifications' => true, 'newsletter' => false]
            ]),
            'last_login_at' => $this->faker->optional()->dateTimeBetween('-30 days'),
            'is_admin' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
