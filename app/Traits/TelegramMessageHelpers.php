<?php

namespace App\Traits;

use App\Models\Message;

/**
 * Shared helpers for Telegram message handling.
 *
 * Eliminates duplication between WidgetMessageController and
 * TelegramWebhookController for body sanitisation and message-type resolution.
 */
trait TelegramMessageHelpers
{
    /**
     * Sanitize text to prevent XSS when rendered in widget.
     */
    protected function sanitizeBody(?string $body): ?string
    {
        if (! is_string($body)) {
            return null;
        }

        $normalized = trim($body);

        if ($normalized === '') {
            return null;
        }

        // Strip HTML tags and convert special characters to HTML entities
        $normalized = strip_tags($normalized);
        $normalized = htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $normalized;
    }

    /**
     * Sanitize visitor name to prevent XSS attacks.
     */
    protected function sanitizeVisitorName(string $name): string
    {
        $cleaned = strip_tags($name);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return mb_substr(trim($cleaned), 0, 100);
    }

    /**
     * Extract text or caption from a Telegram message payload and sanitize it.
     *
     * @param  array<string, mixed>  $telegramMessage
     */
    protected function extractAndSanitizeTelegramBody(array $telegramMessage): ?string
    {
        foreach (['text', 'caption'] as $field) {
            $value = $telegramMessage[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $this->sanitizeBody(trim($value));
            }
        }

        return null;
    }

    /**
     * Resolve message type from attachments.
     *
     * @param  array<int, array<string, mixed>>  $attachments
     */
    protected function resolveMessageType(array $attachments): string
    {
        if ($attachments === []) {
            return Message::TYPE_TEXT;
        }

        $hasOnlyImages = collect($attachments)->every(
            fn (array $attachment): bool => str_starts_with((string) ($attachment['mime_type'] ?? ''), 'image/')
        );

        return $hasOnlyImages ? Message::TYPE_IMAGE : Message::TYPE_FILE;
    }
}
