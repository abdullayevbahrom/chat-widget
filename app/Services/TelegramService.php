<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
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

            return false;
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
}
