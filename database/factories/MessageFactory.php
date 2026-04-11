<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'conversation_id' => Conversation::factory(),
            'message_type' => Message::TYPE_TEXT,
            'body' => fake()->sentence(),
            'attachments' => null,
            'direction' => Message::DIRECTION_INBOUND,
            'is_read' => false,
            'read_at' => null,
            'telegram_message_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this
            ->for(User::factory(), 'sender')
            ->afterMaking(function (Message $message): void {
                if ($message->conversation !== null) {
                    $message->tenant_id = $message->conversation->tenant_id;
                }
                // Ensure sender_type and sender_id are set before the saving hook
                if ($message->sender !== null && $message->sender_type === null) {
                    $message->sender_type = $message->sender::class;
                    $message->sender_id = $message->sender->id ?? null;
                }
            })
            ->afterCreating(function (Message $message): void {
                $conversation = $message->conversation;

                if ($conversation !== null && $message->tenant_id !== $conversation->tenant_id) {
                    $message->forceFill([
                        'tenant_id' => $conversation->tenant_id,
                    ])->saveQuietly();
                }
            });
    }
}
