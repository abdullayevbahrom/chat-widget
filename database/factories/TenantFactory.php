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
            // Profile fields
            'company_name' => $name,
            'company_registration_number' => fake()->optional()->numerify('#########'),
            'tax_id' => fake()->optional()->numerify('###########'),
            'company_address' => fake()->optional()->address(),
            'company_city' => fake()->optional()->city(),
            'company_country' => fake()->optional()->randomElement(['US', 'GB', 'DE', 'FR', 'JP', 'UZ']),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'contact_email' => fake()->optional()->safeEmail(),
            'website' => fake()->optional()->url(),
            'logo_path' => null,
            'primary_contact_name' => fake()->optional()->name(),
            'primary_contact_title' => fake()->optional()->jobTitle(),
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
