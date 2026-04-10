<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDomain>
 */
class ProjectDomainFactory extends Factory
{
    protected $model = ProjectDomain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'domain' => fake()->domainName(),
            'verification_status' => 'pending',
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Mark the domain as verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verification_token' => \Illuminate\Support\Str::random(32),
        ]);
    }

    /**
     * Mark the domain as failed verification.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'failed',
            'verification_error' => 'Verification failed: DNS record not found.',
        ]);
    }

    /**
     * Mark the domain as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
