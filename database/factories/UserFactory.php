<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user should have an admin role.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                // Role will be set after creation due to it being guarded
            ];
        })->afterCreating(function (User $user) {
            $user->forceFill(['role' => 'admin'])->save();
        });
    }

    /**
     * Indicate that the user should have a user role.
     */
    public function regularUser(): static
    {
        return $this->state(function (array $attributes) {
            return [
                // Role will be set after creation due to it being guarded
            ];
        })->afterCreating(function (User $user) {
            $user->forceFill(['role' => 'user'])->save();
        });
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
}
