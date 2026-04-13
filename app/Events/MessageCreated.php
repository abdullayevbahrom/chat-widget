<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-conversation.' . $this->message->conversation?->public_id),
            new PrivateChannel('tenant.' . $this->message->conversation?->tenant_id . '.conversations'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageCreated';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        return [
            'id' => $this->message->public_id,
            'conversation_id' => $this->message->conversation?->public_id,
            'body' => $this->message->body,
            'sender' => [
                'id' => match (true) {
                    $sender instanceof \App\Models\Visitor => $sender->public_id,
                    $sender instanceof \App\Models\User => $sender->public_id ?? $sender->id,
                    $sender instanceof \App\Models\Tenant => $sender->id,
                    default => null,
                },
                'type' => $this->message->sender_type,
            ],
            'direction' => $this->message->direction,
            'message_type' => $this->message->message_type,
            'created_at' => $this->message->created_at->toISOString(),
            'attachments' => $this->message->attachments,
        ];
    }
}
