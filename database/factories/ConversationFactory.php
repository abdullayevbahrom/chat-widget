<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'project_id' => Project::factory(),
            'visitor_id' => null,
            'status' => Conversation::STATUS_OPEN,
            'subject' => fake()->optional()->sentence(),
            'source' => Conversation::SOURCE_WIDGET,
            'telegram_chat_id' => null,
            'assigned_to' => null,
            'last_message_at' => null,
            'closed_at' => null,
            'closed_by' => null,
            'metadata' => null,
        ];
    }

    /**
     * Mark the conversation as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Conversation::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * Mark the conversation as archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Conversation::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Keep tenant_id aligned with the related project tenant.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Conversation $conversation): void {
                if ($conversation->project !== null && $conversation->tenant_id === null) {
                    $conversation->tenant_id = $conversation->project->tenant_id;
                }
            })
            ->afterCreating(function (Conversation $conversation): void {
                $project = $conversation->project;

                if ($project !== null && $conversation->tenant_id !== $project->tenant_id) {
                    $conversation->forceFill([
                        'tenant_id' => $project->tenant_id,
                    ])->saveQuietly();
                }
            });
    }
}
