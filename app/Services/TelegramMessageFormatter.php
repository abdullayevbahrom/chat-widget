<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Project;

/**
 * Formats widget messages for Telegram using MarkdownV2.
 *
 * Telegram MarkdownV2 requires escaping of special characters.
 * This service handles escaping user-supplied content and
 * structures the notification text consistently.
 */
class TelegramMessageFormatter
{
    /**
     * Maximum allowed message length for Telegram.
     */
    public const MAX_MESSAGE_LENGTH = 4096;

    /**
     * Maximum length for the body preview in notifications.
     */
    public const MAX_BODY_LENGTH = 1000;

    /**
     * Characters that must be escaped in MarkdownV2 mode.
     * Note: Backslash MUST be first in the array so it gets escaped
     * before other characters that reference it.
     */
    protected const MARKDOWN_SPECIAL_CHARS = [
        '\\', '_', '*', '[', ']', '(', ')', '~', '`', '>',
        '#', '+', '-', '=', '|', '{', '}', '.', '!',
    ];

    /**
     * Format a message for Telegram notification.
     *
     * @param  Message  $message  The widget message
     * @param  Project  $project  The project the message belongs to
     * @param  array<string, mixed>  $visitorData  Visitor metadata (name, email, etc.)
     * @return array{telegram_text: string, parse_mode: string}
     */
    public static function format(Message $message, Project $project, array $visitorData = []): array
    {
        $visitorName = $visitorData['visitor_name'] ?? ($message->metadata['visitor_name'] ?? 'Anonim');
        $visitorEmail = $visitorData['visitor_email'] ?? ($message->metadata['visitor_email'] ?? null);

        // Truncate FIRST, then escape - this ensures escape sequences aren't broken by truncation
        $truncatedName = self::truncate((string) $visitorName, 100);
        $escapedName = self::escape($truncatedName);
        $escapedEmail = $visitorEmail !== null && $visitorEmail !== ''
            ? self::escape(self::truncate((string) $visitorEmail, 100))
            : "ko'rsatilmagan";
        $projectName = $project->name ?? 'Noma\'lum loyiha';
        $truncatedProjectName = self::truncate((string) $projectName, 100);
        $escapedProjectName = self::escape($truncatedProjectName);

        $parts = [];

        // Header
        $parts[] = '\📨 *Yangi xabar*';
        $parts[] = '';

        // Visitor details
        $parts[] = '\👤 *Ism:* '.$escapedName;
        $parts[] = '\📧 *Email:* '.$escapedEmail;
        $parts[] = '\🏷️ *Loyiha:* '.$escapedProjectName;
        $parts[] = '\🆔 *Suhbat:* \#'.self::escape((string) $message->conversation_id);

        // Message body based on type
        $bodySection = self::formatBody($message);
        if ($bodySection !== '') {
            $parts[] = '';
            $parts[] = $bodySection;
        }

        // Attachments
        $attachmentSection = self::formatAttachments($message);
        if ($attachmentSection !== '') {
            $parts[] = '';
            $parts[] = $attachmentSection;
        }

        // Footer
        $parts[] = '';
        $parts[] = '\_Javob berish uchun shu xabarga reply qiling yoki pastdagi tugmalardan foydalaning\._';

        $text = implode("\n", $parts);

        // Enforce Telegram's 4096 character limit
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 3).'...';
        }

        return [
            'telegram_text' => $text,
            'parse_mode' => 'MarkdownV2',
        ];
    }

    /**
     * Format the message body section with appropriate emoji and structure.
     */
    protected static function formatBody(Message $message): string
    {
        $body = $message->body;

        if ($body === null || $body === '') {
            return '';
        }

        $truncated = self::truncate($body, self::MAX_BODY_LENGTH);
        $escapedBody = self::escape($truncated);

        return match ($message->message_type) {
            Message::TYPE_IMAGE => '\🖼 *Xabar \(rasm\):*'."\n".$escapedBody,
            Message::TYPE_FILE => '\📎 *Xabar \(fayl\):*'."\n".$escapedBody,
            Message::TYPE_SYSTEM => '\ℹ️ *Tizim xabari:*'."\n".$escapedBody,
            default => '\💬 *Xabar:*'."\n".$escapedBody,
        };
    }

    /**
     * Format the attachments section.
     *
     * @return non-empty-string|''
     */
    protected static function formatAttachments(Message $message): string
    {
        $attachments = $message->attachments;

        if (! is_array($attachments) || $attachments === []) {
            return '';
        }

        $lines = ['\📁 *Ilovalar:*'];

        foreach ($attachments as $attachment) {
            $name = self::escape(
                self::truncate($attachment['original_name'] ?? $attachment['name'] ?? 'ilova', 80)
            );
            $lines[] = '\- '.$name;
        }

        return implode("\n", $lines);
    }

    /**
     * Escape text for Telegram MarkdownV2 format.
     *
     * Telegram requires these characters to be escaped with a backslash:
     * _ * [ ] ( ) ~ ` > # + - = | { } . !
     */
    public static function escape(string $text): string
    {
        return str_replace(
            self::MARKDOWN_SPECIAL_CHARS,
            array_map(fn (string $char): string => '\\'.$char, self::MARKDOWN_SPECIAL_CHARS),
            $text
        );
    }

    /**
     * Truncate a string to a maximum length and append ellipsis if needed.
     */
    protected static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 3).'...';
    }
}
