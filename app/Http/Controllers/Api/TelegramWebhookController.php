<?php

namespace App\Http\Controllers\Api;

use App\Events\WidgetMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Services\ConversationService;
use App\Services\MessageAttachmentService;
use App\Services\TelegramBotService;
use App\Services\TelegramInlineKeyboard;
use App\Traits\TelegramMessageHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    use TelegramMessageHelpers;
    /**
     * Official Telegram webhook IP ranges.
     *
     * @see https://core.telegram.org/bots/webhooks#ip-addresses
     */
    protected const TELEGRAM_IP_RANGES = [
        // IPv4 ranges
        '149.154.160.0/20',
        '91.108.4.0/22',
        '5.255.255.0/24',
        // IPv6 ranges
        '2001:67c:4e8::/48',
        '2001:b28:f23c::/47',
        '2001:b28:f23f::/48',
    ];

    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected MessageAttachmentService $messageAttachmentService,
        protected ConversationService $conversationService,
    ) {}

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // Use the real client IP directly from the server variable
        // instead of $request->ip() which can be manipulated via proxy headers.
        // When behind a trusted reverse proxy, TRUSTED_PROXIES middleware
        // will already have set the correct client IP in $request->ip().
        // For direct connections (no proxy), $_SERVER['REMOTE_ADDR'] is authoritative.
        $clientIp = $this->resolveClientIp($request);

        $forwardedFor = $request->header('X-Forwarded-For');
        $realIp = $request->header('X-Real-IP');

        if ($forwardedFor !== null || $realIp !== null) {
            // Sanitize proxy headers to prevent log injection
            $sanitizedForwardedFor = preg_replace('/[^\d.,\s:]/', '', $forwardedFor ?? '');
            $sanitizedRealIp = preg_replace('/[^\d.\s:]/', '', $realIp ?? '');

            Log::info('Telegram webhook with proxy headers.', [
                'client_ip' => $clientIp,
                'x_forwarded_for' => $sanitizedForwardedFor !== '' ? $sanitizedForwardedFor : null,
                'x_real_ip' => $sanitizedRealIp !== '' ? $sanitizedRealIp : null,
                'tenant_slug' => $tenantSlug,
            ]);
        }

        // N+1 fix: Load tenant and bot settings in a single query chain
        $setting = TelegramBotSetting::whereHas('tenant', function ($query) use ($tenantSlug) {
            $query->where('slug', $tenantSlug);
        })
            ->with('tenant')
            ->first();

        if ($setting === null) {
            Log::warning('Telegram webhook received for unknown tenant or unconfigured bot.', [
                'tenant_slug' => $tenantSlug,
                'ip' => $clientIp,
            ]);

            return response()->json(['ok' => false, 'error' => 'Tenant or bot not found'], 404);
        }

        if ($setting->webhook_secret === null) {
            Log::warning('Telegram webhook received for setting without webhook_secret.', [
                'tenant_id' => $setting->tenant_id,
                'setting_id' => $setting->id,
                'ip' => $clientIp,
            ]);

            return response()->json(['ok' => false, 'error' => 'Webhook not configured'], 400);
        }

        if ($secretToken === null) {
            Log::warning('Telegram webhook received without secret token header.', [
                'tenant_id' => $setting->tenant_id,
                'setting_id' => $setting->id,
                'ip' => $clientIp,
            ]);

            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (! hash_equals($setting->webhook_secret, $secretToken)) {
            Log::warning('Telegram webhook received with invalid secret token.', [
                'tenant_id' => $setting->tenant_id,
                'setting_id' => $setting->id,
                'ip' => $clientIp,
            ]);

            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (! $setting->is_active) {
            Log::info('Telegram webhook received for inactive bot.', [
                'tenant_id' => $setting->tenant_id,
                'setting_id' => $setting->id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $payload = $request->all();

        // Verify the request originates from Telegram's IP range
        if (! $this->isTelegramIp($clientIp)) {
            Log::warning('Telegram webhook received from untrusted IP.', [
                'tenant_id' => $setting->tenant_id,
                'setting_id' => $setting->id,
                'client_ip' => $clientIp,
            ]);

            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        // Replay attack protection: reject duplicate update_id
        $updateId = $payload['update_id'] ?? null;

        if ($updateId !== null) {
            $cacheKey = "telegram_update_{$setting->id}_{$updateId}";

            if (Cache::has($cacheKey)) {
                Log::warning('Telegram webhook replay detected: duplicate update_id.', [
                    'tenant_id' => $setting->tenant_id,
                    'setting_id' => $setting->id,
                    'update_id' => $updateId,
                ]);

                // Return ok=true to prevent Telegram from retrying
                return response()->json(['ok' => true, 'result' => true]);
            }

            // Mark this update_id as processed (24 hour TTL)
            Cache::put($cacheKey, true, now()->addDay());
        }

        Log::info('Telegram webhook received.', [
            'channel' => 'telegram',
            'action' => 'webhook_received',
            'tenant_id' => $setting->tenant_id,
            'setting_id' => $setting->id,
            'update_id' => $payload['update_id'] ?? null,
            'has_message' => isset($payload['message']),
            'has_callback_query' => isset($payload['callback_query']),
        ]);

        // Handle callback_query (inline button presses)
        if (isset($payload['callback_query']) && is_array($payload['callback_query'])) {
            return $this->handleCallbackQuery($payload['callback_query'], $setting);
        }

        if (! isset($payload['message']) || ! is_array($payload['message'])) {
            return response()->json(['ok' => true, 'result' => true]);
        }

        $messagePayload = $payload['message'];
        $chatId = isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null;

        if ($chatId === null || $chatId === '') {
            Log::warning('Telegram webhook message is missing chat context.', [
                'tenant_id' => $setting->tenant_id,
                'update_id' => $payload['update_id'] ?? null,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $this->syncChatBinding($setting, $chatId);

        if ($setting->chat_id !== $chatId) {
            Log::warning('Ignoring Telegram message from an unexpected chat.', [
                'tenant_id' => $setting->tenant_id,
                'expected_chat_id' => $setting->chat_id,
                'actual_chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Handle /close command
        $text = $messagePayload['text'] ?? null;

        if (is_string($text) && trim($text) === '/close') {
            $this->handleCloseCommand($setting, $chatId);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $replyToTelegramId = $messagePayload['reply_to_message']['message_id'] ?? null;

        if (! is_int($replyToTelegramId)) {
            Log::info('Ignoring Telegram message because it is not a reply to a widget notification.', [
                'tenant_id' => $setting->tenant_id,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
                'chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $sourceMessage = Message::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->where('telegram_message_id', $replyToTelegramId)
            ->first();

        if ($sourceMessage === null) {
            Log::warning('Ignoring Telegram reply because the original widget message could not be found.', [
                'tenant_id' => $setting->tenant_id,
                'reply_to_telegram_message_id' => $replyToTelegramId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $conversation = Conversation::withoutGlobalScopes()->find($sourceMessage->conversation_id);
        $project = $conversation?->project()->withoutGlobalScopes()->first();

        if ($conversation === null || $project === null) {
            Log::warning('Ignoring Telegram reply because conversation context is incomplete.', [
                'tenant_id' => $setting->tenant_id,
                'source_message_id' => $sourceMessage->id,
                'conversation_id' => $sourceMessage->conversation_id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $attachments = blank($setting->bot_token)
            ? []
            : $this->messageAttachmentService->storeTelegramAttachments(
                $this->telegramBotService,
                $setting->bot_token,
                $messagePayload,
                $project,
                $conversation
            );
        $body = $this->extractAndSanitizeTelegramBody($messagePayload);

        if ($body === null && $attachments === []) {
            Log::info('Ignoring Telegram reply because it contains neither text nor attachments.', [
                'tenant_id' => $setting->tenant_id,
                'conversation_id' => $conversation->id,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Auto-reopen closed conversations when admin replies via Telegram
        if ($conversation->isClosed()) {
            try {
                $this->conversationService->reopenConversation($conversation);

                Log::info('Auto-reopened closed conversation via Telegram reply.', [
                    'tenant_id' => $setting->tenant_id,
                    'conversation_id' => $conversation->id,
                ]);
            } catch (\LogicException $e) {
                Log::warning('Could not reopen conversation via Telegram reply.', [
                    'tenant_id' => $setting->tenant_id,
                    'conversation_id' => $conversation->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $adminMessage = Message::withoutGlobalScopes()->create([
            'tenant_id' => $setting->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_type' => $setting->tenant->getMorphClass(),
            'sender_id' => $setting->tenant_id,
            'message_type' => $this->resolveMessageType($attachments),
            'body' => $body,
            'attachments' => $attachments !== [] ? $attachments : null,
            'direction' => Message::DIRECTION_OUTBOUND,
            'is_read' => false,
            'metadata' => [
                'telegram_update_id' => $payload['update_id'] ?? null,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
                'reply_to_message_id' => $replyToTelegramId,
                'chat_id' => $chatId,
                'from' => [
                    'id' => $messagePayload['from']['id'] ?? null,
                    'username' => $messagePayload['from']['username'] ?? null,
                    'first_name' => $messagePayload['from']['first_name'] ?? null,
                    'last_name' => $messagePayload['from']['last_name'] ?? null,
                ],
            ],
        ]);

        Log::info('Stored Telegram admin reply as widget message.', [
            'tenant_id' => $setting->tenant_id,
            'conversation_id' => $conversation->id,
            'message_id' => $adminMessage->id,
            'telegram_message_id' => $messagePayload['message_id'] ?? null,
            'attachment_count' => count($attachments),
        ]);

        // Derive agent name from Telegram user info
        $agentName = $this->resolveAgentName($messagePayload['from'] ?? []);

        // Broadcast the admin reply to the widget in real time via Reverb
        broadcast(new WidgetMessageSent($conversation, $adminMessage, $agentName))->toOthers();

        return response()->json(['ok' => true, 'result' => true]);
    }

    protected function syncChatBinding(TelegramBotSetting $setting, string $chatId): void
    {
        if ($setting->chat_id === $chatId) {
            return;
        }

        if (blank($setting->chat_id)) {
            Log::info('Binding Telegram tenant settings to the first observed chat.', [
                'setting_id' => $setting->id,
                'tenant_id' => $setting->tenant_id,
                'chat_id' => $chatId,
            ]);

            $setting->forceFill(['chat_id' => $chatId])->save();
        }
    }

    /**
     * Handle the /close command — close the most recent open conversation.
     */
    protected function handleCloseCommand(TelegramBotSetting $setting, string $chatId): void
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->where('telegram_chat_id', $chatId)
            ->open()
            ->latest('last_message_at')
            ->first();

        if ($conversation === null) {
            // Try to find any open conversation for this tenant
            $conversation = Conversation::withoutGlobalScopes()
                ->where('tenant_id', $setting->tenant_id)
                ->open()
                ->latest('last_message_at')
                ->first();
        }

        if ($conversation === null) {
            Log::info('Telegram /close command: no open conversation found.', [
                'tenant_id' => $setting->tenant_id,
                'chat_id' => $chatId,
            ]);

            return;
        }

        $this->conversationService->closeConversation($conversation);

        // Notify the Telegram chat
        if (filled($setting->bot_token)) {
            try {
                $this->telegramBotService->sendMessage(
                    $setting->bot_token,
                    $chatId,
                    "✅ Suhbat #{$conversation->id} yopildi."
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send Telegram close notification.', [
                    'channel' => 'telegram',
                    'action' => 'close_command',
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]);
            }
        }

        Log::info('Telegram /close command processed.', [
            'tenant_id' => $setting->tenant_id,
            'conversation_id' => $conversation->id,
        ]);
    }

    // ============================================================
    // Callback Query Handlers (inline button presses)
    // ============================================================

    /**
     * Handle an inline button callback query.
     *
     * @param  array<string, mixed>  $callbackQuery  The callback_query payload from Telegram
     * @param  TelegramBotSetting  $setting  The tenant's bot setting
     */
    protected function handleCallbackQuery(array $callbackQuery, TelegramBotSetting $setting): JsonResponse
    {
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;
        $from = $callbackQuery['from'] ?? [];
        $message = $callbackQuery['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if ($callbackQueryId === null || $data === null) {
            Log::warning('Callback query missing required fields.', [
                'tenant_id' => $setting->tenant_id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Authorization: Verify the Telegram user is an allowed admin
        $fromUserId = $from['id'] ?? null;

        if ($fromUserId === null || ! $this->isTelegramAdminAllowed($setting, (string) $fromUserId)) {
            Log::warning('Callback query from unauthorized Telegram user.', [
                'tenant_id' => $setting->tenant_id,
                'callback_query_id' => $callbackQueryId,
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $setting->bot_token,
                    $callbackQueryId,
                    "Ruxsat berilmagan",
                    true
                );
            } catch (\Exception $e) {
                Log::warning('Failed to answer callback query.', [
                    'channel' => 'telegram',
                    'action' => 'callback_query',
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]);
            }

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Parse action:tenant_id:conversation_id:signature using TelegramInlineKeyboard
        $parsed = TelegramInlineKeyboard::parseCallbackData($data);

        if ($parsed === null) {
            Log::warning('Invalid callback data format.', [
                'tenant_id' => $setting->tenant_id,
                'data' => $data,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $setting->bot_token,
                    $callbackQueryId,
                    "Noto'g'ri so'rov formati",
                    true
                );
            } catch (\Exception $e) {
                Log::warning('Failed to answer callback query.', [
                    'channel' => 'telegram',
                    'action' => 'callback_query',
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]);
            }

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Verify HMAC signature to prevent callback forgery
        if (! TelegramInlineKeyboard::verifyCallbackSignature(
            $parsed['tenant_id'],
            $parsed['conversation_id'],
            $parsed['signature']
        )) {
            Log::warning('Invalid callback data signature.', [
                'tenant_id' => $setting->tenant_id,
                'data' => $data,
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $setting->bot_token,
                    $callbackQueryId,
                    "Noto'g'ri imzo",
                    true
                );
            } catch (\Exception $e) {
                Log::warning('Failed to answer callback query.', [
                    'channel' => 'telegram',
                    'action' => 'callback_query',
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]);
            }

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Ensure tenant_id matches the setting's tenant
        if ($parsed['tenant_id'] !== $setting->tenant_id) {
            Log::warning('Callback data tenant mismatch.', [
                'setting_tenant_id' => $setting->tenant_id,
                'callback_tenant_id' => $parsed['tenant_id'],
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $setting->bot_token,
                    $callbackQueryId,
                    "Noto'g'ri tenant",
                    true
                );
            } catch (\Exception $e) {
                Log::warning('Failed to answer callback query.', [
                    'channel' => 'telegram',
                    'action' => 'callback_query',
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]);
            }

            return response()->json(['ok' => true, 'result' => true]);
        }

        $action = $parsed['action'];
        $conversationId = $parsed['conversation_id'];

        Log::info('Processing callback query.', [
            'tenant_id' => $setting->tenant_id,
            'action' => $action,
            'conversation_id' => $conversationId,
            'from_user_id' => $from['id'] ?? null,
        ]);

        return match ($action) {
            'reply' => $this->handleCallbackReply($setting, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            'close' => $this->handleCallbackClose($setting, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            'assign' => $this->handleCallbackAssign($setting, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            default => $this->handleUnknownCallback($setting, $callbackQueryId, $action),
        };
    }

    /**
     * Handle the "Reply" callback — acknowledges the callback.
     * The actual reply is handled via the standard reply-to-message flow.
     */
    protected function handleCallbackReply(
        TelegramBotSetting $setting,
        string $callbackQueryId,
        int $conversationId,
        mixed $chatId,
        mixed $messageId,
        array $from,
    ): JsonResponse {
        try {
            $this->telegramBotService->answerCallbackQuery(
                $setting->bot_token,
                $callbackQueryId
            );
        } catch (\Exception $e) {
            Log::warning('Failed to answer callback query (reply).', [
                'channel' => 'telegram',
                'action' => 'callback_reply',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
        }

        Log::info('Callback query: reply acknowledged.', [
            'conversation_id' => $conversationId,
        ]);

        return response()->json(['ok' => true, 'result' => true]);
    }

    /**
     * Handle the "Close" callback — closes the conversation.
     *
     * Verifies the Telegram user is mapped to a system User in the same tenant.
     */
    protected function handleCallbackClose(
        TelegramBotSetting $setting,
        string $callbackQueryId,
        int $conversationId,
        mixed $chatId,
        mixed $messageId,
        array $from,
    ): JsonResponse {
        // Authorization: verify Telegram user is mapped to a tenant user
        $telegramUserId = $from['id'] ?? null;

        if ($telegramUserId !== null) {
            $mappedUser = \App\Models\User::withoutGlobalScopes()
                ->where('telegram_user_id', (string) $telegramUserId)
                ->where('tenant_id', $setting->tenant_id)
                ->exists();

            if (! $mappedUser) {
                Log::warning('Telegram close callback from unmapped user.', [
                    'tenant_id' => $setting->tenant_id,
                    'telegram_user_id' => $telegramUserId,
                ]);

                // Still allow close if the user is in the admin whitelist
                if (! $this->isTelegramAdminAllowed($setting, (string) $telegramUserId)) {
                    $this->answerCallbackWithError($setting, $callbackQueryId, 'Ruxsat berilmagan');

                    return response()->json(['ok' => true, 'result' => true]);
                }
            }
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->find($conversationId);

        if ($conversation === null) {
            $this->answerCallbackWithError($setting, $callbackQueryId, 'Suhbat topilmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        if (! $conversation->canTransitionTo(Conversation::STATUS_CLOSED)) {
            $this->answerCallbackWithError(
                $setting,
                $callbackQueryId,
                "Suhbatni yopib bo'lmaydi (holat: {$conversation->status})"
            );

            return response()->json(['ok' => true, 'result' => true]);
        }

        try {
            $this->conversationService->closeConversation($conversation);

            $this->telegramBotService->answerCallbackQuery(
                $setting->bot_token,
                $callbackQueryId,
                'Suhbat yopildi'
            );

            // Update the keyboard to show closed state
            if ($chatId !== null && $messageId !== null && filled($setting->bot_token)) {
                $this->editMessageKeyboard(
                    $setting,
                    (string) $chatId,
                    (int) $messageId,
                    TelegramInlineKeyboard::buildClosedKeyboard()
                );
            }

            Log::info('Conversation closed via Telegram callback.', [
                'conversation_id' => $conversationId,
            ]);
        } catch (\LogicException $e) {
            Log::warning('Could not close conversation via callback.', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            $this->answerCallbackWithError($setting, $callbackQueryId, 'Yopishda xatolik yuz berdi');
        }

        return response()->json(['ok' => true, 'result' => true]);
    }

    /**
     * Handle the "Assign to me" callback — assigns the conversation.
     *
     * Requires the Telegram user to be mapped to a system User with
     * is_super_admin = true via telegram_user_id.
     */
    protected function handleCallbackAssign(
        TelegramBotSetting $setting,
        string $callbackQueryId,
        int $conversationId,
        mixed $chatId,
        mixed $messageId,
        array $from,
    ): JsonResponse {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->with('project')
            ->find($conversationId);

        if ($conversation === null) {
            $this->answerCallbackWithError($setting, $callbackQueryId, 'Suhbat topilmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Authorization: require Telegram user to be mapped to a super admin User
        $telegramUserId = $from['id'] ?? null;

        if ($telegramUserId === null) {
            $this->answerCallbackWithError($setting, $callbackQueryId, 'Foydalanuvchi aniqlanmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        $user = \App\Models\User::withoutGlobalScopes()
            ->where('telegram_user_id', (string) $telegramUserId)
            ->where('tenant_id', $setting->tenant_id)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($user === null || ! $user->isSuperAdmin()) {
            Log::warning('Telegram assign callback from non-super-admin user.', [
                'tenant_id' => $setting->tenant_id,
                'telegram_user_id' => $telegramUserId,
                'user_found' => $user !== null,
                'is_super_admin' => $user?->is_super_admin ?? false,
            ]);

            $this->answerCallbackWithError($setting, $callbackQueryId, 'Ruxsat berilmagan');

            return response()->json(['ok' => true, 'result' => true]);
        }

        try {
            $this->conversationService->assignConversation($conversation, $user);

            $this->telegramBotService->answerCallbackQuery(
                $setting->bot_token,
                $callbackQueryId,
                'Suhbat sizga tayinlandi'
            );

            // Update the keyboard to show assigned state
            if ($chatId !== null && $messageId !== null && filled($setting->bot_token) && $conversation->project) {
                $this->editMessageKeyboard(
                    $setting,
                    (string) $chatId,
                    (int) $messageId,
                    TelegramInlineKeyboard::buildAfterAssignment($conversation, $conversation->project)
                );
            }

            Log::info('Conversation assigned via Telegram callback.', [
                'conversation_id' => $conversationId,
                'assigned_to' => $user->id,
            ]);
        } catch (\LogicException $e) {
            Log::warning('Could not assign conversation via callback.', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            $this->answerCallbackWithError($setting, $callbackQueryId, 'Tayinlashda xatolik yuz berdi');
        }

        return response()->json(['ok' => true, 'result' => true]);
    }

    /**
     * Handle an unknown callback action.
     */
    protected function handleUnknownCallback(
        TelegramBotSetting $setting,
        string $callbackQueryId,
        string $action,
    ): JsonResponse {
        try {
            $this->telegramBotService->answerCallbackQuery(
                $setting->bot_token,
                $callbackQueryId,
                "Noma'lum amal: {$action}"
            );
        } catch (\Exception $e) {
            Log::warning('Failed to answer unknown callback query.', [
                'channel' => 'telegram',
                'action' => 'unknown_callback',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
        }

        Log::warning('Unknown callback action received.', [
            'action' => $action,
            'tenant_id' => $setting->tenant_id,
        ]);

        return response()->json(['ok' => true, 'result' => true]);
    }

    /**
     * Resolve agent display name from Telegram user information.
     *
     * Priority: username > full name > first name > "Telegram Admin"
     *
     * @param  array<string, mixed>  $from  Telegram user info from message payload
     */
    protected function resolveAgentName(array $from): ?string
    {
        if ($from === []) {
            return null;
        }

        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? null;
        $lastName = $from['last_name'] ?? null;

        // Priority 1: Use username if available
        if (is_string($username) && trim($username) !== '') {
            return '@'.trim($username);
        }

        // Priority 2: Use full name (first + last)
        if (is_string($firstName) && trim($firstName) !== '') {
            $name = trim($firstName);

            if (is_string($lastName) && trim($lastName) !== '') {
                $name .= ' '.trim($lastName);
            }

            return $name;
        }

        // Fallback: Generic name
        return 'Telegram Admin';
    }

    /**
     * Check if the given Telegram user ID is allowed as an admin.
     *
     * Only allows users explicitly listed in telegram_admin_ids.
     * If the list is empty or not configured, access is denied.
     */
    protected function isTelegramAdminAllowed(TelegramBotSetting $setting, string $fromUserId): bool
    {
        $adminIds = $setting->telegram_admin_ids;

        if (is_array($adminIds) && count($adminIds) > 0) {
            // Convert all IDs to strings for comparison
            $allowedIds = array_map('strval', $adminIds);

            return in_array($fromUserId, $allowedIds, true);
        }

        // Deny access if no admin list is configured
        return false;
    }

    /**
     * Answer a callback query with an error message.
     */
    protected function answerCallbackWithError(
        TelegramBotSetting $setting,
        string $callbackQueryId,
        string $errorMessage,
    ): void {
        try {
            if (filled($setting->bot_token)) {
                $this->telegramBotService->answerCallbackQuery(
                    $setting->bot_token,
                    $callbackQueryId,
                    $errorMessage,
                    true
                );
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send callback error response.', [
                'channel' => 'telegram',
                'action' => 'callback_error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
        }
    }

    /**
     * Edit a message's inline keyboard via Telegram API.
     *
     * @param  TelegramBotSetting  $setting  The bot setting
     * @param  string  $chatId  The chat ID
     * @param  int  $messageId  The message ID to edit
     * @param  array  $replyMarkup  The new inline keyboard markup
     */
    protected function editMessageKeyboard(
        TelegramBotSetting $setting,
        string $chatId,
        int $messageId,
        array $replyMarkup,
    ): void {
        if (blank($setting->bot_token)) {
            return;
        }

        try {
            $this->telegramBotService->editMessageReplyMarkup(
                $setting->bot_token,
                $chatId,
                $messageId,
                $replyMarkup
            );
        } catch (\Exception $e) {
            Log::warning('Failed to edit message keyboard.', [
                'channel' => 'telegram',
                'action' => 'edit_keyboard',
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
        }
    }

    /**
     * Resolve the real client IP address.
     *
     * Uses $_SERVER['REMOTE_ADDR'] directly to avoid relying on
     * potentially untrusted proxy headers. When the request comes
     * through a trusted reverse proxy (configured via TRUSTED_PROXIES),
     * Laravel's middleware will have already set the correct client IP.
     * For direct connections, REMOTE_ADDR is the authoritative source.
     */
    protected function resolveClientIp(Request $request): string
    {
        // If trusted proxies are configured, Laravel's middleware has
        // already resolved the real IP from X-Forwarded-For headers.
        // In that case, $request->ip() is safe to use.
        $trustedProxies = array_values(array_filter(
            array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
            static fn (string $proxy): bool => $proxy !== ''
        ));

        if ($trustedProxies !== []) {
            // Trusted proxy is configured — Laravel middleware already
            // resolved the real client IP from forwarded headers
            return $request->ip() ?? $this->getServerRemoteAddr();
        }

        // No trusted proxy configured — use REMOTE_ADDR directly
        // This is the safest option for direct connections
        return $this->getServerRemoteAddr();
    }

    /**
     * Get the remote address from the server variable.
     *
     * Fallback to '0.0.0.0' if not available (should never happen).
     */
    protected function getServerRemoteAddr(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if the given IP belongs to Telegram's webhook IP ranges.
     *
     * @see https://core.telegram.org/bots/webhooks#ip-addresses
     */
    protected function isTelegramIp(string $ip): bool
    {
        foreach (self::TELEGRAM_IP_RANGES as $range) {
            [$subnet, $mask] = array_pad(explode('/', $range), 2, $this->isIpv6($ip) ? '128' : '32');
            $mask = (int) $mask;

            // IPv6 handling
            if ($this->isIpv6($ip) || $this->isIpv6($subnet)) {
                if (! $this->isIpv6($ip) || ! $this->isIpv6($subnet)) {
                    continue; // Mismatched IP types
                }

                $ipBin = $this->ipv6ToBinary($ip);
                $subnetBin = $this->ipv6ToBinary($subnet);

                if ($ipBin === null || $subnetBin === null) {
                    continue;
                }

                // Compare first $mask bits
                if (substr($ipBin, 0, $mask) === substr($subnetBin, 0, $mask)) {
                    return true;
                }

                continue;
            }

            if ($mask === 32) {
                // Exact IP match
                if ($ip === $subnet) {
                    return true;
                }

                continue;
            }

            // IPv4 CIDR match
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                continue;
            }

            $maskLong = -1 << (32 - $mask);

            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP address is IPv6.
     */
    protected function isIpv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Convert an IPv6 address to its binary representation.
     */
    protected function ipv6ToBinary(string $ip): ?string
    {
        $packed = @inet_pton($ip);

        if ($packed === false || $packed === null) {
            return null;
        }

        $binary = '';

        for ($i = 0; $i < strlen($packed); $i++) {
            $binary .= str_pad(decbin(ord($packed[$i])), 8, '0', STR_PAD_LEFT);
        }

        return $binary;
    }
}
