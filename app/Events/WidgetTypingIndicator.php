<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetTypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Conversation  $conversation  The conversation the typing event belongs to.
     * @param  bool  $isTyping  Whether the agent is currently typing.
     * @param  string|null  $agentName  The agent's display name.
     */
    public function __construct(
        public Conversation $conversation,
        public bool $isTyping,
        public ?string $agentName = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Using PrivateChannel for proper authorization. Widget visitors
     * authenticate via the bootstrap token during Echo auth.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('widget.conversation.' . $this->conversation->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'widget.typing';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'typing' => $this->isTyping,
            'agent_name' => $this->agentName ? Str::limit($this->agentName, 100) : null,
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
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
        ]);
    }
}
