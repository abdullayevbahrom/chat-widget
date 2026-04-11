<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'primary_domain' => fake()->optional()->domainName(),
            'settings' => [
                'widget' => [
                    'theme' => 'dark',
                    'position' => 'bottom-right',
                    'width' => 360,
                    'height' => 520,
                    'primary_color' => '#8B5CF6',
                    'custom_css' => null,
                ],
            ],
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory with a widget key.
     */
    public function withWidgetKey(): static
    {
        return $this->afterMaking(function (Project $project) {
            $plaintextKey = 'wsk_' . bin2hex(random_bytes(16));
            $hash = hash('sha256', $plaintextKey);
            $prefix = substr($plaintextKey, 0, 8);

            $project->widget_key_hash = $hash;
            $project->widget_key_prefix = $prefix;
            $project->widget_key_generated_at = now();
        });
    }

    /**
     * Mark the project as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
