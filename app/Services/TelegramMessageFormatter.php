<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Project;

class TelegramMessageFormatter
{
    public const MAX_MESSAGE_LENGTH = 4096;

    public const MAX_BODY_LENGTH = 1000;

    /**
     * @param  array<string, mixed>  $visitorData
     * @return array{telegram_text: string, parse_mode: string}
     */
    public static function format(Message $message, Project $project, array $visitorData = []): array
    {
        $visitorName = $visitorData['visitor_name'] ?? ($message->metadata['visitor_name'] ?? 'Visitor');
        $visitorEmail = $visitorData['visitor_email'] ?? ($message->metadata['visitor_email'] ?? null);
    
        // HTML uchun escape (PHP'ning standart funksiyasi)
        $eName = self::e($visitorName);
        $eEmail = $visitorEmail ? self::e($visitorEmail) : 'not provided';
        $eProject = self::e($project->name ?? 'Unknown');
        $eConvId = self::e((string)$message->conversation_id);
    
        $parts = [
            '📨 <b>New message</b>',
            '',
            '👤 <b>Visitor:</b> ' . $eName,
            '📧 <b>Email:</b> ' . $eEmail,
            '🏷️ <b>Project:</b> ' . $eProject,
            '🆔 <b>Conversation:</b> #' . $eConvId,
        ];
    
        $body = self::e(self::truncate($message->body ?? '', self::MAX_BODY_LENGTH));
        if ($body !== '') {
            $parts[] = '';
            $parts[] = "💬 <b>Message:</b>\n" . $body;
        }
    
        $parts[] = '';
        $parts[] = '<i>Reply to this message or use the buttons below.</i>';
    
        return [
            'telegram_text' => implode("\n", $parts),
            'parse_mode' => 'HTML', // MarkdownV2 emas, HTML!
        ];
    }
    
    // Yordamchi metod
    protected static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected static function formatBody(Message $message): string
    {
        $body = $message->body;
    
        if ($body === null || $body === '') {
            return '';
        }
    
        // HTML uchun xavfsiz holatga keltiramiz
        $escapedBody = self::e(self::truncate($body, self::MAX_BODY_LENGTH));
    
        return match ($message->message_type) {
            Message::TYPE_IMAGE => "🖼 <b>Message (image):</b>\n" . $escapedBody,
            Message::TYPE_FILE => "📎 <b>Message (file):</b>\n" . $escapedBody,
            Message::TYPE_SYSTEM => "ℹ️ <b>System message:</b>\n" . $escapedBody,
            default => "💬 <b>Message:</b>\n" . $escapedBody,
        };
    }

    /**
     * @return non-empty-string|''
     */
    protected static function formatAttachments(Message $message): string
    {
        $attachments = $message->attachments;

        if (!is_array($attachments) || $attachments === []) {
            return '';
        }

        $lines = ['📁 <b>Attachments:</b>'];

        foreach ($attachments as $attachment) {
            $name = $attachment['original_name'] ?? $attachment['name'] ?? 'attachment';
            $lines[] = '- ' . self::e(self::truncate($name, 80));
        }

        return implode("\n", $lines);
    }

    protected static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
    
        return mb_substr($text, 0, $max - 3) . '...';
    }
}
