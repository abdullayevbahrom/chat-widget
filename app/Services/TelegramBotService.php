<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    /**
     * Default Telegram Bot API base URL.
     */
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = config('telegram.bot_api_base_url', 'https://api.telegram.org/bot');
    }

    /**
     * Get bot information using the getMe API method.
     *
     * @param  string  $token  The bot token
     * @return array Bot information (id, first_name, username, etc.)
     *
     * @throws \Exception If the API request fails
     */
    public function getBotInfo(string $token): array
    {
        $response = Http::timeout(10)
            ->get("{$this->apiBaseUrl}{$token}/getMe");

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API getMe failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json('result', []);
    }

    /**
     * Set a webhook for the bot.
     *
     * @param  string  $token  The bot token
     * @param  string  $webhookUrl  The webhook URL
     * @param  string|null  $secret  Optional secret token for webhook validation
     * @return array Webhook setup result
     *
     * @throws \Exception If the API request fails
     */
    public function setWebhook(string $token, string $webhookUrl, ?string $secret = null): array
    {
        $payload = ['url' => $webhookUrl];

        if ($secret !== null) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/setWebhook", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API setWebhook failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'webhook_url' => $webhookUrl,
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json('result', []);
    }

    /**
     * Delete the bot's webhook.
     *
     * @param  string  $token  The bot token
     * @return array Webhook deletion result
     *
     * @throws \Exception If the API request fails
     */
    public function deleteWebhook(string $token): array
    {
        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/deleteWebhook");

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API deleteWebhook failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json('result', []);
    }

    /**
     * Get webhook information.
     *
     * @param  string  $token  The bot token
     * @return array Webhook information (url, has_custom_certificate, pending_update_count, etc.)
     *
     * @throws \Exception If the API request fails
     */
    public function getWebhookInfo(string $token): array
    {
        $response = Http::timeout(10)
            ->get("{$this->apiBaseUrl}{$token}/getWebhookInfo");

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API getWebhookInfo failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json('result', []);
    }

    /**
     * Validate a Telegram bot token format.
     *
     * Telegram bot tokens follow the pattern: digits:alphanumeric-with-hyphens/underscores
     * Example: 123456789:ABCdef-GHIjkl_MNOpqrSTUvwxYZ
     *
     * @param  string  $token  The token to validate
     * @return bool Whether the token format is valid
     */
    public function validateToken(string $token): bool
    {
        return (bool) preg_match('/^\d+:[\w-]+$/', $token);
    }

    /**
     * Maximum allowed message length for Telegram.
     */
    public const MAX_MESSAGE_LENGTH = 4096;

    /**
     * Send a message to a Telegram chat.
     *
     * @param  string  $token  The bot token
     * @param  string|int  $chatId  The chat ID
     * @param  string  $text  The message text
     * @param  string|null  $parseMode  Parse mode (Markdown, HTML, etc.)
     * @param  array|null  $replyMarkup  Inline keyboard or reply markup
     * @return array API response
     *
     * @throws \Exception If the API request fails
     * @throws \InvalidArgumentException If text exceeds 4096 characters
     */
    public function sendMessage(
        string $token,
        string|int $chatId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Telegram message text cannot exceed '.self::MAX_MESSAGE_LENGTH.' characters.'
            );
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/sendMessage", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API sendMessage failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'chat_id' => $chatId,
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json();
    }

    /**
     * Answer a callback query (acknowledge inline button press).
     *
     * @param  string  $token  The bot token
     * @param  string  $callbackQueryId  The callback query ID
     * @param  string|null  $text  Optional notification text (max 200 chars)
     * @param  bool  $showAlert  Whether to show an alert popup
     * @return array API response
     *
     * @throws \Exception If the API request fails
     */
    public function answerCallbackQuery(
        string $token,
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
    ): array {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = mb_substr($text, 0, 200);
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/answerCallbackQuery", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API answerCallbackQuery failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'callback_query_id' => $callbackQueryId,
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json();
    }

    /**
     * Edit text of an existing message (used to update inline keyboards).
     *
     * @param  string  $token  The bot token
     * @param  string|int  $chatId  The chat ID
     * @param  int  $messageId  The message ID to edit
     * @param  string  $text  New message text
     * @param  string|null  $parseMode  Parse mode (MarkdownV2, HTML, etc.)
     * @param  array|null  $replyMarkup  New inline keyboard markup
     * @return array API response
     *
     * @throws \Exception If the API request fails
     * @throws \InvalidArgumentException If text exceeds 4096 characters
     */
    public function editMessageText(
        string $token,
        string|int $chatId,
        int $messageId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Telegram edit message text cannot exceed '.self::MAX_MESSAGE_LENGTH.' characters.'
            );
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/editMessageText", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API editMessageText failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json();
    }

    /**
     * Edit only the reply markup (inline keyboard) of an existing message.
     * This is preferred over editMessageText when only buttons need updating.
     *
     * @param  string  $token  The bot token
     * @param  string|int  $chatId  The chat ID
     * @param  int  $messageId  The message ID to edit
     * @param  array  $replyMarkup  New inline keyboard markup
     * @return array API response
     *
     * @throws \Exception If the API request fails
     */
    public function editMessageReplyMarkup(
        string $token,
        string|int $chatId,
        int $messageId,
        array $replyMarkup,
    ): array {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/editMessageReplyMarkup", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API editMessageReplyMarkup failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'description' => $description,
                'status' => $response->status(),
            ]);

            throw new \Exception("Telegram API error: {$description}");
        }

        return $response->json();
    }

    public function getFilePath(string $token, string $fileId): ?string
    {
        $response = Http::timeout(10)
            ->get("{$this->apiBaseUrl}{$token}/getFile", [
                'file_id' => $fileId,
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            Log::warning('Telegram Bot API getFile failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'file_id' => $fileId,
                'status' => $response->status(),
                'description' => $response->json('description', 'Unknown error'),
            ]);

            return null;
        }

        $filePath = $response->json('result.file_path');

        return is_string($filePath) && $filePath !== '' ? $filePath : null;
    }

    public function downloadFile(string $token, string $filePath): ?string
    {
        $downloadBaseUrl = str_replace('/bot', '/file/bot', $this->apiBaseUrl);
        $response = Http::timeout(20)->get("{$downloadBaseUrl}{$token}/{$filePath}");

        if (! $response->successful()) {
            Log::warning('Telegram file download failed', [
                'token_prefix' => $this->sanitizeToken($token),
                'file_path' => $filePath,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->body();
    }

    /**
     * Check the webhook status and return detailed information.
     *
     * @param  string  $token  The bot token
     * @return array Webhook status information
     *
     * @throws \Exception If the API request fails
     */
    public function checkWebhookStatus(string $token): array
    {
        $info = $this->getWebhookInfo($token);

        $hasUrl = isset($info['url']) && $info['url'] !== '';
        $hasError = isset($info['last_error_date']) && isset($info['last_error_message']);

        return [
            'url' => $info['url'] ?? null,
            'has_custom_certificate' => $info['has_custom_certificate'] ?? false,
            'pending_update_count' => $info['pending_update_count'] ?? 0,
            'last_error_date' => $info['last_error_date'] ?? null,
            'last_error_message' => $info['last_error_message'] ?? null,
            'max_connections' => $info['max_connections'] ?? null,
            'allowed_updates' => $info['allowed_updates'] ?? [],
            'ip_address' => $info['ip_address'] ?? null,
            'is_active' => $hasUrl && ! $hasError,
            'status' => $hasUrl
                ? ($hasError ? 'error' : 'active')
                : 'not_set',
        ];
    }

    /**
     * Sanitize a bot token for safe logging.
     * Replaces the middle portion of the token with asterisks.
     */
    protected function sanitizeToken(string $token): string
    {
        return preg_replace('/^(\d+:).{4,}([A-Za-z0-9_-]{4})$/', '$1****$2', $token);
    }
}
