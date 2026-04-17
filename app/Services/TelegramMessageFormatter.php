<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Project;

class TelegramMessageFormatter
{
    public const MAX_MESSAGE_LENGTH = 4096;

    public const MAX_BODY_LENGTH = 1000;

    protected const MARKDOWN_SPECIAL_CHARS = [
        '\\', '_', '*', '[', ']', '(', ')', '~', '`', '>',
        '#', '+', '-', '=', '|', '{', '}', '.', '!',
    ];

    /**
     * @param  array<string, mixed>  $visitorData
     * @return array{telegram_text: string, parse_mode: string}
     */
    public static function format(Message $message, Project $project, array $visitorData = []): array
    {
        $visitorName = $visitorData['visitor_name'] ?? ($message->metadata['visitor_name'] ?? 'Visitor');
        $visitorEmail = $visitorData['visitor_email'] ?? ($message->metadata['visitor_email'] ?? null);

        $escapedName = self::escape(self::truncate((string) $visitorName, 100));
        $escapedEmail = $visitorEmail !== null && $visitorEmail !== ''
            ? self::escape(self::truncate((string) $visitorEmail, 100))
            : 'not provided';
        $escapedProjectName = self::escape(self::truncate((string) ($project->name ?? 'Unknown project'), 100));

        $parts = [
            '\📨 *New message*',
            '',
            '\👤 *Visitor:* '.$escapedName,
            '\📧 *Email:* '.$escapedEmail,
            '\🏷️ *Project:* '.$escapedProjectName,
            '\🆔 *Conversation:* \#'.self::escape((string) $message->conversation_id),
        ];

        $bodySection = self::formatBody($message);
        if ($bodySection !== '') {
            $parts[] = '';
            $parts[] = $bodySection;
        }

        $attachmentSection = self::formatAttachments($message);
        if ($attachmentSection !== '') {
            $parts[] = '';
            $parts[] = $attachmentSection;
        }

        $parts[] = '';
        $parts[] = '\_Reply to this message or use the buttons below\._';

        $text = implode("\n", $parts);

        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 3).'...';
        }

        return [
            'telegram_text' => $text,
            'parse_mode' => 'MarkdownV2',
        ];
    }

    protected static function formatBody(Message $message): string
    {
        $body = $message->body;

        if ($body === null || $body === '') {
            return '';
        }

        $escapedBody = self::escape(self::truncate($body, self::MAX_BODY_LENGTH));

        return match ($message->message_type) {
            Message::TYPE_IMAGE => '\🖼 *Message \(image\):*'."\n".$escapedBody,
            Message::TYPE_FILE => '\📎 *Message \(file\):*'."\n".$escapedBody,
            Message::TYPE_SYSTEM => '\ℹ️ *System message:*'."\n".$escapedBody,
            default => '\💬 *Message:*'."\n".$escapedBody,
        };
    }

    /**
     * @return non-empty-string|''
     */
    protected static function formatAttachments(Message $message): string
    {
        $attachments = $message->attachments;

        if (! is_array($attachments) || $attachments === []) {
            return '';
        }

        $lines = ['\📁 *Attachments:*'];

        foreach ($attachments as $attachment) {
            $lines[] = '\- '.self::escape(
                self::truncate($attachment['original_name'] ?? $attachment['name'] ?? 'attachment', 80)
            );
        }

        return implode("\n", $lines);
    }

    public static function escape(string $text): string
    {
        return str_replace(
            self::MARKDOWN_SPECIAL_CHARS,
            array_map(fn (string $char): string => '\\'.$char, self::MARKDOWN_SPECIAL_CHARS),
            $text
        );
    }

    protected static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 3).'...';
    }
}
