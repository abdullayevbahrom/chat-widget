<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
            'plan' => fake()->randomElement(['free', 'basic', 'pro', 'enterprise']),
            'subscription_expires_at' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'settings' => null,
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant has an expired subscription.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_expires_at' => now()->subMonths(2),
        ]);
    }

    /**
     * Indicate that the tenant is on the free plan.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'free',
            'subscription_expires_at' => null,
        ]);
    }
}
