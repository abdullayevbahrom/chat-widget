<?php

namespace App\Events;

use App\Exceptions\BroadcastFailedException;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WidgetMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Conversation $conversation,
        public Message $message,
        public ?string $agentName = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Using PrivateChannel for both channels to ensure
     * proper authorization. Widget visitors authenticate
     * via the bootstrap token during Echo auth.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->conversation->tenant_id.'.conversations'),
            new PrivateChannel('widget.conversation.'.$this->conversation->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'widget.message-sent';
    }

    /**
     * Get the data to broadcast.
     *
     * Only return fields needed by the widget client.
     * Sensitive fields are excluded. Message body is
     * returned as-is from the database (already sanitized
     * at ingestion time) and truncated to prevent abuse.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $attachments = collect($this->message->attachments ?? [])
            ->map(fn ($attachment) => [
                'id' => $attachment['id'] ?? null,
                'original_name' => $attachment['original_name'] ?? $attachment['name'] ?? 'attachment',
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
                'url' => $attachment['url'] ?? null,
            ])
            ->all();

        // Return body as-is from DB (already sanitized at ingestion).
        // Only truncate if excessively long to prevent oversized payloads.
        $body = $this->truncateBody($this->message->body);

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->conversation->id,
                'type' => $this->message->isInbound() ? 'visitor' : 'admin',
                'body' => $body,
                'attachments' => $attachments,
                'created_at' => $this->message->created_at->toISOString(),
            ],
            'conversation_id' => $this->conversation->id,
            'status' => $this->conversation->status,
            'agent_name' => $this->agentName,
        ];
    }

    /**
     * Truncate message body to prevent oversized broadcast payloads.
     *
     * The body is already sanitized at ingestion time (strip_tags + htmlspecialchars
     * for Telegram messages, validated for visitor messages), so no additional
     * sanitization is needed here.
     */
    protected function truncateBody(?string $body, int $maxLength = 5000): ?string
    {
        if ($body === null) {
            return null;
        }

        if (mb_strlen($body) > $maxLength) {
            return mb_substr($body, 0, $maxLength).'…';
        }

        return $body;
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
            'message_id' => $this->message->id,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
        ]);
    }
}
