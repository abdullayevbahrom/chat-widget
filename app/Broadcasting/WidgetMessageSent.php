<?php

namespace App\Broadcasting;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Using PrivateChannel for better security. Widget visitors
     * authenticate via the widget bootstrap token during Echo auth.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('widget.conversation.' . $this->message->conversation_id),
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
     * Sensitive fields (visitor_email, ip_address, metadata, etc.) are excluded.
     *
     * Admin message body is sanitized and truncated to prevent abuse.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $attachments = collect($this->message->attachments ?? [])
            ->map(fn($attachment) => [
                'id' => $attachment['id'] ?? null,
                'original_name' => $attachment['original_name'] ?? $attachment['name'] ?? 'attachment',
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
                'url' => $attachment['url'] ?? null,
            ])
            ->all();

        // Sanitize and truncate admin message body to prevent abuse
        $body = $this->sanitizeAndTruncateBody($this->message->body);

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'type' => $this->message->isInbound() ? 'visitor' : 'admin',
                'body' => $body,
                'attachments' => $attachments,
                'created_at' => $this->message->created_at->toISOString(),
            ],
            'conversation_id' => $this->message->conversation_id,
            'status' => $this->message->conversation?->status,
        ];
    }

    /**
     * Sanitize message body by stripping HTML tags and truncating to a safe length.
     *
     * This prevents admins from sending potentially malicious HTML/JS payloads
     * and keeps broadcast payloads reasonably sized.
     */
    protected function sanitizeAndTruncateBody(?string $body, int $maxLength = 5000): ?string
    {
        if ($body === null) {
            return null;
        }

        // Strip all HTML tags to prevent injection
        $cleaned = strip_tags($body);

        // Decode HTML entities that may have been double-encoded
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Truncate to max length to prevent oversized payloads
        if (mb_strlen($cleaned) > $maxLength) {
            $cleaned = mb_substr($cleaned, 0, $maxLength) . '…';
        }

        return $cleaned;
    }
}
