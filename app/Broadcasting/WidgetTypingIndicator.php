<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetTypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $conversationId  The conversation the typing event belongs to.
     * @param  bool  $typing  Whether the agent is currently typing.
     * @param  string|null  $agentName  The agent's display name.
     */
    public function __construct(
        public int $conversationId,
        public bool $typing = true,
        public ?string $agentName = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('widget.conversation.' . $this->conversationId),
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
            'typing' => $this->typing,
            'agent_name' => $this->agentName,
            'conversation_id' => $this->conversationId,
        ];
    }
}
