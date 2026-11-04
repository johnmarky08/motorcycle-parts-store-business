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
            'role' => fake()->randomElement(['inventory_clerk', 'sales_personnel']),
            'status' => 'active',
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role('admin');
    }

    public function instructor(): static
    {
        return $this->role('instructor');
    }

    public function inventoryClerk(): static
    {
        return $this->role('inventory_clerk');
    }

    public function salesPersonnel(): static
    {
        return $this->role('sales_personnel');
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /** A registration waiting for administrator approval. */
    public function pending(string $requestedRole = 'sales_personnel'): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'requested_role' => $requestedRole,
            'affiliation' => fake()->company(),
            'registration_status_token' => Str::random(48),
            'submitted_at' => now(),
        ]);
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
