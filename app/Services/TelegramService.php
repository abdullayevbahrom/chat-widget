<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send message to Telegram admin chat.
     */
    public function sendMessage(Conversation $conversation, string $text, ?Message $sourceMessage = null): ?int
    {
        $project = $conversation->project;

        if ($project === null) {
            Log::warning('Cannot send Telegram message: project not found.', [
                'conversation_id' => $conversation->id,
            ]);

            return null;
        }

        $admins = $project->getTelegramAdmins();

        if (! $project->telegram_bot_token || $admins === []) {
            Log::warning('Cannot send Telegram message: bot not configured.', [
                'conversation_id' => $conversation->id,
                'project_id' => $project->id,
            ]);

            return null;
        }

        $token = $project->telegram_bot_token;

        // Format message with metadata
        $message = sprintf(
            "💬 *New Message*\n\n%s\n\n*Conversation:* #%d\n*Domain:* %s",
            $text,
            $conversation->id,
            $project->domain ?? 'unknown'
        );

        // Add inline keyboard with conversation ID for reply tracking
        $replyMarkup = json_encode(TelegramInlineKeyboard::buildForConversation($conversation, $project));
        $firstMessageId = null;

        foreach ($admins as $admin) {
            try {
                $response = Http::timeout(10)->post(
                    "https://api.telegram.org/bot{$token}/sendMessage",
                    [
                        'chat_id' => $admin['chat_id'],
                        'text' => $message,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $replyMarkup,
                    ]
                );

                if (! $response->successful()) {
                    Log::error('Telegram API error', [
                        'conversation_id' => $conversation->id,
                        'chat_id' => $admin['chat_id'],
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $data = $response->json();
                $messageId = $data['result']['message_id'] ?? null;

                if ($messageId === null) {
                    continue;
                }

                $firstMessageId ??= $messageId;

                DB::table('telegram_message_references')->updateOrInsert(
                    [
                        'chat_id' => (string) $admin['chat_id'],
                        'telegram_message_id' => $messageId,
                    ],
                    [
                        'tenant_id' => $conversation->tenant_id,
                        'project_id' => $project->id,
                        'message_id' => $sourceMessage?->id ?? ($conversation->messages()->latest('id')->value('id') ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Telegram send failed', [
                    'conversation_id' => $conversation->id,
                    'chat_id' => $admin['chat_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $firstMessageId;
    }

    /**
     * Send reply from admin to visitor via Telegram.
     */
    public function sendReply(string $token, string $chatId, string $text): bool
    {
        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram reply failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Mirror an admin reply into every configured Telegram admin chat.
     */
    public function mirrorAdminReply(Message $message, ?string $agentName = null): void
    {
        $conversation = $message->conversation()->withoutGlobalScopes()->first();
        $project = $conversation?->project()->withoutGlobalScopes()->first();

        if ($conversation === null || $project === null) {
            return;
        }

        $admins = $project->getTelegramAdmins();
        $token = $project->telegram_bot_token;

        if (blank($token) || $admins === []) {
            return;
        }

        $replyMarkup = TelegramInlineKeyboard::buildForConversation($conversation, $project);
        $sourceMessage = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('direction', Message::DIRECTION_OUTBOUND)
            ->latest('id')
            ->first();

        $references = collect();

        if ($sourceMessage !== null) {
            $references = DB::table('telegram_message_references')
                ->where('tenant_id', $conversation->tenant_id)
                ->where('message_id', $sourceMessage->id)
                ->get()
                ->keyBy(fn (object $reference): string => (string) $reference->chat_id);
        }

        $text = $this->formatAdminReplyNotification($message, $conversation, $project, $agentName);

        foreach ($admins as $admin) {
            $chatId = (string) ($admin['chat_id'] ?? '');

            if ($chatId === '') {
                continue;
            }

            try {
                $payload = [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ];

                $reference = $references->get($chatId);

                if ($reference !== null && isset($reference->telegram_message_id)) {
                    $payload['reply_to_message_id'] = (int) $reference->telegram_message_id;
                    $payload['allow_sending_without_reply'] = true;
                }

                $response = Http::timeout(10)->post(
                    "https://api.telegram.org/bot{$token}/sendMessage",
                    $payload
                );

                if (! $response->successful()) {
                    Log::warning('Failed to mirror admin reply to Telegram.', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $telegramMessageId = $response->json('result.message_id');

                if ($telegramMessageId !== null) {
                    DB::table('telegram_message_references')->updateOrInsert(
                        [
                            'chat_id' => $chatId,
                            'telegram_message_id' => $telegramMessageId,
                        ],
                        [
                            'tenant_id' => $conversation->tenant_id,
                            'project_id' => $project->id,
                            'message_id' => $message->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Admin reply mirror to Telegram failed.', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get bot info (username, name).
     */
    public function getBotInfo(string $token): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'username' => '@'.($data['result']['username'] ?? ''),
                    'first_name' => $data['result']['first_name'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get bot info', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function formatAdminReplyNotification(
        Message $message,
        Conversation $conversation,
        Project $project,
        ?string $agentName = null
    ): string {
        $senderName = trim((string) ($agentName ?: ($message->metadata['agent_name'] ?? 'Admin')));
        $body = trim((string) ($message->body ?? ''));

        if ($body === '') {
            $body = '[Attachment]';
        }

        return implode("\n\n", [
            '↩️ <b>Admin Reply</b>',
            '<b>From:</b> '.$this->escapeHtml($senderName),
            '<b>Project:</b> '.$this->escapeHtml((string) ($project->name ?? 'Project')).
                "\n".'<b>Conversation:</b> #'.$conversation->id,
            $this->escapeHtml($body),
        ]);
    }

    protected function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
