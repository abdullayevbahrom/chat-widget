<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConversationClosed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Conversation $conversation,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->conversation->tenant_id.'.conversations'),
            new PrivateChannel('private-conversation.'.$this->conversation->public_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'conversation.closed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->public_id,
                'status' => $this->conversation->status,
                'closed_at' => $this->conversation->closed_at?->toISOString(),
            ],
        ];
    }

    /**
     * Handle a broadcast failure.
     */
    public function broadcastFailed(\Throwable $exception): void
    {
        Log::error('WebSocket broadcast failed', [
            'channel' => 'websocket',
            'event' => self::class,
            'conversation_id' => $this->conversation->public_id,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
        ]);
    }
}
