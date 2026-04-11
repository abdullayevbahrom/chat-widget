<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending error notifications to admins.
 *
 * Supports Telegram and Slack notification channels
 * with deduplication to prevent notification spam.
 */
class ErrorNotificationService
{
    /**
     * Cache TTL for deduplication (5 minutes).
     */
    protected const DEDUP_TTL = 300;

    /**
     * Maximum message length for Telegram.
     */
    protected const MAX_TELEGRAM_LENGTH = 4096;

    /**
     * Send a notification to admins about an error.
     */
    public function notifyAdmins(string $message, array $context = []): void
    {
        // Deduplication: prevent sending the same error within 5 minutes
        $dedupKey = $this->buildDedupKey($message, $context);

        if (Cache::has($dedupKey)) {
            Log::debug('Skipping duplicate error notification', [
                'dedup_key' => $dedupKey,
            ]);

            return;
        }

        // Mark as sent
        Cache::put($dedupKey, true, self::DEDUP_TTL);

        // Try Telegram first
        $this->notifyViaTelegram($message, $context);

        // Try Slack as fallback
        $this->notifyViaSlack($message, $context);
    }

    /**
     * Send notification via Telegram to admin users.
     */
    protected function notifyViaTelegram(string $message, array $context): void
    {
        $adminIds = config('telegram.admin_user_ids', []);

        if (empty($adminIds)) {
            return;
        }

        $botToken = config('telegram.bot_token');

        if (blank($botToken)) {
            return;
        }

        // Truncate message if needed (Telegram limit: 4096 chars)
        $formattedMessage = $this->formatTelegramMessage($message, $context);

        foreach ($adminIds as $adminId) {
            try {
                Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $adminId,
                    'text' => $formattedMessage,
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to send Telegram admin notification', [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send notification via Slack webhook.
     */
    protected function notifyViaSlack(string $message, array $context): void
    {
        $webhookUrl = env('LOG_SLACK_WEBHOOK_URL');

        if (blank($webhookUrl)) {
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'text' => "⚠️ {$message}",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*⚠️ Error Notification*\n{$message}",
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Slack admin notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format a message for Telegram with HTML formatting.
     */
    protected function formatTelegramMessage(string $message, array $context): string
    {
        $output = "⚠️ <b>{$message}</b>\n\n";

        if (isset($context['tenant_id'])) {
            $output .= "Tenant: {$context['tenant_id']}\n";
        }

        if (isset($context['message_id'])) {
            $output .= "Message: {$context['message_id']}\n";
        }

        if (isset($context['error'])) {
            $error = mb_substr($context['error'], 0, 1000);
            $output .= "Xato: {$error}\n";
        }

        if (isset($context['attempts'])) {
            $output .= "Urinishlar: {$context['attempts']}\n";
        }

        $output .= "\nVaqt: " . now()->toISOString();

        // Truncate to Telegram limit
        if (mb_strlen($output) > self::MAX_TELEGRAM_LENGTH) {
            $output = mb_substr($output, 0, self::MAX_TELEGRAM_LENGTH - 3) . '...';
        }

        return $output;
    }

    /**
     * Build a deduplication cache key.
     */
    protected function buildDedupKey(string $message, array $context): string
    {
        $data = $message . json_encode($context);

        return 'error_notification_' . md5($data);
    }
}
