<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Support\Facades\URL;

/**
 * Builds inline keyboard markup for Telegram notifications.
 *
 * Telegram callback_data has a 64-byte limit, so conversation IDs
 * are kept as plain integers (well within the limit).
 *
 * Security: callback_data includes an HMAC signature to prevent
 * unauthorized callback forgery across tenants.
 */
class TelegramInlineKeyboard
{
    /**
     * Build an inline keyboard for a conversation notification.
     *
     * @param  Conversation  $conversation  The conversation
     * @param  Project  $project  The project (for dashboard URL)
     * @return array{inline_keyboard: array<array<array<string, string>>>}
     */
    public static function buildForConversation(Conversation $conversation, Project $project): array
    {
        $conversationId = $conversation->id;
        $dashboardUrl = self::buildDashboardUrl($conversation, $project);
        $signature = self::signCallbackData($conversation->tenant_id, $conversationId);

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '💬 Reply',
                        // 'url' => self::buildMiniAppUrl($conversation, $project),
                        'web_app' => [
                            'url' => self::buildMiniAppUrl($conversation, $project),
                        ],
                    ],
                    [
                        'text' => '🔒 Close',
                        'callback_data' => 'close:'.$conversation->tenant_id.':'.$conversationId.':'.$signature,
                    ],
                ],
                [
                    [
                        'text' => '👤 Assign to me',
                        'callback_data' => 'assign:'.$conversation->tenant_id.':'.$conversationId.':'.$signature,
                    ],
                    [
                        'text' => '📋 Dashboard',
                        'url' => $dashboardUrl,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a keyboard with disabled buttons (for closed conversations).
     *
     * @return array{inline_keyboard: array<array<array<string, string>>>}
     */
    public static function buildClosedKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Suhbat yopildi',
                        'callback_data' => 'closed_ack',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a keyboard with the assign button removed (after assignment).
     *
     * @param  Conversation  $conversation  The conversation
     * @param  Project  $project  The project
     * @return array{inline_keyboard: array<array<array<string, string>>>}
     */
    public static function buildAfterAssignment(Conversation $conversation, Project $project): array
    {
        $dashboardUrl = self::buildDashboardUrl($conversation, $project);
        $signature = self::signCallbackData($conversation->tenant_id, $conversation->id);

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '💬 Reply',
                        // 'url' => self::buildMiniAppUrl($conversation, $project),
                        'web_app' => [
                            'url' => self::buildMiniAppUrl($conversation, $project),
                        ],
                    ],
                    [
                        'text' => '🔒 Close',
                        'callback_data' => 'close:'.$conversation->tenant_id.':'.$conversation->id.':'.$signature,
                    ],
                ],
                [
                    [
                        'text' => '✅ Assigned',
                        'callback_data' => 'assigned_ack',
                    ],
                    [
                        'text' => '📋 Dashboard',
                        'url' => $dashboardUrl,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the dashboard URL for a conversation.
     */
    protected static function buildDashboardUrl(Conversation $conversation, Project $project): string
    {
        try {
            $baseUrl = function_exists('config') ? config('app.url') : null;
        } catch (\Throwable $e) {
            $baseUrl = null;
        }

        $baseUrl ??= $_ENV['APP_URL'] ?? 'https://app.example.com';

        return rtrim($baseUrl, '/').'/dashboard/conversations/'.$conversation->id;
    }

    protected static function buildMiniAppUrl(Conversation $conversation, Project $project): string
    {
        try {
            return URL::signedRoute('telegram.mini-app', [
                'project' => $project->id,
                'conversation' => $conversation->public_id ?: $conversation->id,
            ]);
        } catch (\Throwable) {
            $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://app.example.com', '/');

            return $baseUrl.'/dashboard/conversations/'.$conversation->id;
        }
    }

    /**
     * Create an HMAC signature for callback data authorization.
     *
     * The signature prevents cross-tenant callback forgery.
     * We use a short prefix (8 chars) to stay within Telegram's 64-byte callback_data limit.
     *
     * @throws \RuntimeException if APP_KEY is not configured
     */
    public static function signCallbackData(int $tenantId, int $conversationId): string
    {
        $payload = $tenantId.':'.$conversationId;

        try {
            $secret = function_exists('config') ? config('app.key') : null;
        } catch (\Throwable $e) {
            $secret = null;
        }

        $secret ??= $_ENV['APP_KEY'] ?? null;

        if ($secret === null || $secret === '') {
            throw new \RuntimeException(
                'APP_KEY is not configured. Cannot sign Telegram callback data.'
            );
        }

        $hmac = hash_hmac('sha256', $payload, $secret);

        // Use first 16 characters for stronger security while staying within 64-byte limit
        return substr($hmac, 0, 16);
    }

    /**
     * Verify the HMAC signature from callback data.
     */
    public static function verifyCallbackSignature(int $tenantId, int $conversationId, string $signature): bool
    {
        $expected = self::signCallbackData($tenantId, $conversationId);

        return hash_equals($expected, $signature);
    }

    /**
     * Parse callback data into its components.
     *
     * @param  string  $data  e.g. "reply:1:42:abc12345"
     * @return array{action: string, tenant_id: int, conversation_id: int, signature: string}|null
     */
    public static function parseCallbackData(string $data): ?array
    {
        $parts = explode(':', $data);

        if (count($parts) !== 4) {
            return null;
        }

        [$action, $tenantIdStr, $convIdStr, $signature] = $parts;
        $tenantId = filter_var($tenantIdStr, FILTER_VALIDATE_INT);
        $conversationId = filter_var($convIdStr, FILTER_VALIDATE_INT);

        if ($tenantId === false || $conversationId === false) {
            return null;
        }

        return [
            'action' => $action,
            'tenant_id' => (int) $tenantId,
            'conversation_id' => (int) $conversationId,
            'signature' => $signature,
        ];
    }
}
