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
     * Send a message to a Telegram chat.
     *
     * @param  string  $token  The bot token
     * @param  string|int  $chatId  The chat ID
     * @param  string  $text  The message text
     * @param  string|null  $parseMode  Parse mode (Markdown, HTML, etc.)
     * @return array API response
     *
     * @throws \Exception If the API request fails
     */
    public function sendMessage(string $token, string|int $chatId, string $text, ?string $parseMode = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $response = Http::timeout(10)
            ->post("{$this->apiBaseUrl}{$token}/sendMessage", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = $response->json('description', 'Unknown error');
            Log::error('Telegram Bot API sendMessage failed', [
                'chat_id' => $chatId,
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
                'file_path' => $filePath,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->body();
    }
}
