<?php

namespace App\Http\Controllers\Api;

use App\Events\WidgetMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
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
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    use TelegramMessageHelpers;

    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected MessageAttachmentService $messageAttachmentService,
        protected ConversationService $conversationService,
    ) {
    }

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request, Project $project): JsonResponse
    {

        if (!$project->is_active) {
            Log::info('Telegram webhook received for inactive bot.', [
                'tenant_id' => $project->tenant_id,
                'id' => $project->id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $payload = $request->all();

        // Replay attack protection: reject duplicate update_id
        $updateId = $payload['update_id'] ?? null;

        if ($updateId !== null) {
            $cacheKey = "telegram_update_{$project->id}_{$updateId}";

            if (Cache::has($cacheKey)) {
                Log::warning('Telegram webhook replay detected: duplicate update_id.', [
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'update_id' => $updateId,
                ]);

                return response()->json(['ok' => true, 'result' => true]);
            }

            // Mark this update_id as processed (24 hour TTL)
            Cache::put($cacheKey, true, now()->addDay());
        }

        Log::info('Telegram webhook received.', [
            'channel' => 'telegram',
            'action' => 'webhook_received',
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'update_id' => $payload['update_id'] ?? null,
            'has_message' => isset($payload['message']),
            'has_callback_query' => isset($payload['callback_query']),
        ]);

        // Handle callback_query (inline button presses)
        if (isset($payload['callback_query']) && is_array($payload['callback_query'])) {
            return $this->handleCallbackQuery($payload['callback_query'], $project);
        }

        if (!isset($payload['message']) || !is_array($payload['message'])) {
            return response()->json(['ok' => true, 'result' => true]);
        }

        $messagePayload = $payload['message'];
        $chatId = isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null;

        if ($chatId === null || $chatId === '') {
            Log::warning('Telegram webhook message is missing chat context.', [
                'tenant_id' => $project->tenant_id,
                'update_id' => $payload['update_id'] ?? null,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $this->syncChatBinding($project, $chatId);

        if ($project->telegram_chat_id !== $chatId) {
            Log::warning('Ignoring Telegram message from an unexpected chat.', [
                'tenant_id' => $project->tenant_id,
                'expected_chat_id' => $project->telegram_chat_id,
                'actual_chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Handle /close command
        $text = $messagePayload['text'] ?? null;

        if (is_string($text) && trim($text) === '/close') {
            $this->handleCloseCommand($project, $chatId);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $replyToTelegramId = $messagePayload['reply_to_message']['message_id'] ?? null;

        if (!is_int($replyToTelegramId)) {
            Log::info('Ignoring Telegram message because it is not a reply to a widget notification.', [
                'tenant_id' => $project->tenant_id,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
                'chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $sourceMessage = Message::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->where('telegram_message_id', $replyToTelegramId)
            ->first();

        if ($sourceMessage === null) {
            Log::warning('Ignoring Telegram reply because the original widget message could not be found.', [
                'tenant_id' => $project->tenant_id,
                'reply_to_telegram_message_id' => $replyToTelegramId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $conversation = Conversation::withoutGlobalScopes()->find($sourceMessage->conversation_id);
        $project = $conversation?->project()->withoutGlobalScopes()->first();

        if ($conversation === null || $project === null) {
            Log::warning('Ignoring Telegram reply because conversation context is incomplete.', [
                'tenant_id' => $project->tenant_id,
                'source_message_id' => $sourceMessage->id,
                'conversation_id' => $sourceMessage->conversation_id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $attachments = blank($project->telegram_bot_token)
            ? []
            : $this->messageAttachmentService->storeTelegramAttachments(
                $this->telegramBotService,
                $project->telegram_bot_token,
                $messagePayload,
                $project,
                $conversation
            );
        $body = $this->extractAndSanitizeTelegramBody($messagePayload);

        if ($body === null && $attachments === []) {
            Log::info('Ignoring Telegram reply because it contains neither text nor attachments.', [
                'tenant_id' => $project->tenant_id,
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
                    'tenant_id' => $project->tenant_id,
                    'conversation_id' => $conversation->id,
                ]);
            } catch (\LogicException $e) {
                Log::warning('Could not reopen conversation via Telegram reply.', [
                    'tenant_id' => $project->tenant_id,
                    'conversation_id' => $conversation->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $adminMessage = Message::withoutGlobalScopes()->create([
            'tenant_id' => $project->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_type' => $project->tenant->getMorphClass(),
            'sender_id' => $project->tenant_id,
            'message_type' => $this->resolveMessageType($attachments),
            'body' => $body,
            'attachments' => $attachments !== [] ? $attachments : null,
            'direction' => Message::DIRECTION_INBOUND,
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
            'tenant_id' => $project->tenant_id,
            'conversation_id' => $conversation->id,
            'message_id' => $adminMessage->id,
            'telegram_message_id' => $messagePayload['message_id'] ?? null,
            'attachment_count' => count($attachments),
        ]);

        // Ensure conversation has a public_id for WebSocket broadcasting
        if (blank($conversation->public_id)) {
            $conversation->public_id = (string) Str::uuid();
            $conversation->saveQuietly();
            Log::info('Assigned public_id to conversation.', [
                'conversation_id' => $conversation->id,
                'public_id' => $conversation->public_id,
            ]);
        }

        // Derive agent name from Telegram user info
        $agentName = $this->resolveAgentName($messagePayload['from'] ?? []);

        // Broadcast the admin reply to the widget in real time via Reverb
        // Only WidgetMessageSent is needed (widget.js listens to both widget.message-sent and MessageCreated)
        try {
            broadcast(new WidgetMessageSent($conversation, $adminMessage, $agentName));

            Log::info('Broadcast admin reply to WebSocket completed.', [
                'conversation_public_id' => $conversation->public_id,
                'message_public_id' => $adminMessage->public_id,
                'channel' => 'private-conversation.' . $conversation->public_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Broadcast admin reply to WebSocket failed (non-critical).', [
                'conversation_id' => $conversation->id,
                'message_id' => $adminMessage->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'result' => true]);
    }

    protected function syncChatBinding(Project $project, string $chatId): void
    {
        if ($project->chat_id === $chatId) {
            return;
        }

        if (blank($project->telegram_chat_id)) {
            Log::info('Binding Telegram tenant settings to the first observed chat.', [
                'project_id' => $project->id,
                'tenant_id' => $project->tenant_id,
                'chat_id' => $chatId,
            ]);

            $project->forceFill(['telegram_chat_id' => $chatId])->save();
        }
    }

    /**
     * Handle the /close command — close the most recent open conversation.
     */
    protected function handleCloseCommand(Project $project, string $chatId): void
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->where('telegram_chat_id', $chatId)
            ->open()
            ->latest('last_message_at')
            ->first();

        if ($conversation === null) {
            // Try to find any open conversation for this tenant
            $conversation = Conversation::withoutGlobalScopes()
                ->where('tenant_id', $project->tenant_id)
                ->open()
                ->latest('last_message_at')
                ->first();
        }

        if ($conversation === null) {
            Log::info('Telegram /close command: no open conversation found.', [
                'tenant_id' => $project->tenant_id,
                'chat_id' => $chatId,
            ]);

            return;
        }

        $this->conversationService->closeConversation($conversation);

        // Notify the Telegram chat
        if (filled($project->telegram_bot_token)) {
            try {
                $this->telegramBotService->sendMessage(
                    $project->telegram_bot_token,
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
            'tenant_id' => $project->tenant_id,
            'conversation_id' => $conversation->id,
        ]);
    }

    protected function handleCallbackQuery(array $callbackQuery, Project $project): JsonResponse
    {
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;
        $from = $callbackQuery['from'] ?? [];
        $message = $callbackQuery['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if ($callbackQueryId === null || $data === null) {
            Log::warning('Callback query missing required fields.', [
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Authorization: Verify the Telegram user is an allowed admin
        $fromUserId = $from['id'] ?? null;

        if ($fromUserId === null || !$this->isTelegramAdminAllowed($project, (string) $fromUserId)) {
            Log::warning('Callback query from unauthorized Telegram user.', [
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'callback_query_id' => $callbackQueryId,
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $project->telegram_bot_token,
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
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'data' => $data,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $project->telegram_bot_token,
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
        if (
            !TelegramInlineKeyboard::verifyCallbackSignature(
                $parsed['tenant_id'],
                $parsed['conversation_id'],
                $parsed['signature']
            )
        ) {
            Log::warning('Invalid callback data signature.', [
                'tenant_id' => $project->tenant_id,
                'data' => $data,
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $project->telegram_bot_token,
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

        if ($parsed['tenant_id'] !== $project->tenant_id) {
            Log::warning('Callback data tenant mismatch.', [
                'project_tenant_id' => $project->tenant_id,
                'callback_tenant_id' => $parsed['tenant_id'],
                'from_user_id' => $fromUserId,
            ]);

            try {
                $this->telegramBotService->answerCallbackQuery(
                    $project->telegram_bot_token,
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
            'tenant_id' => $project->tenant_id,
            'action' => $action,
            'conversation_id' => $conversationId,
            'from_user_id' => $from['id'] ?? null,
        ]);

        return match ($action) {
            'reply' => $this->handleCallbackReply($project, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            'close' => $this->handleCallbackClose($project, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            'assign' => $this->handleCallbackAssign($project, $callbackQueryId, $conversationId, $chatId, $messageId, $from),
            default => $this->handleUnknownCallback($project, $callbackQueryId, $action),
        };
    }

    /**
     * Handle the "Reply" callback — acknowledges the callback.
     * The actual reply is handled via the standard reply-to-message flow.
     */
    protected function handleCallbackReply(
        Project $project,
        string $callbackQueryId,
        int $conversationId,
        mixed $chatId,
        mixed $messageId,
        array $from,
    ): JsonResponse {
        try {
            $this->telegramBotService->answerCallbackQuery(
                $project->telegram_bot_token,
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
        Project $project,
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
                ->where('tenant_id', $project->tenant_id)
                ->exists();

            if (!$mappedUser) {
                Log::warning('Telegram close callback from unmapped user.', [
                    'tenant_id' => $project->tenant_id,
                    'telegram_user_id' => $telegramUserId,
                ]);

                // Still allow close if the user is in the admin whitelist
                if (!$this->isTelegramAdminAllowed($project, (string) $telegramUserId)) {
                    $this->answerCallbackWithError($project, $callbackQueryId, 'Ruxsat berilmagan');

                    return response()->json(['ok' => true, 'result' => true]);
                }
            }
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->find($conversationId);

        if ($conversation === null) {
            $this->answerCallbackWithError($project, $callbackQueryId, 'Suhbat topilmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        if (!$conversation->canTransitionTo(Conversation::STATUS_CLOSED)) {
            $this->answerCallbackWithError(
                $project,
                $callbackQueryId,
                "Suhbatni yopib bo'lmaydi (holat: {$conversation->status})"
            );

            return response()->json(['ok' => true, 'result' => true]);
        }

        try {
            $this->conversationService->closeConversation($conversation);

            $this->telegramBotService->answerCallbackQuery(
                $project->telegram_bot_token,
                $callbackQueryId,
                'Suhbat yopildi'
            );

            // Update the keyboard to show closed state
            if ($chatId !== null && $messageId !== null && filled($project->telegram_bot_token)) {
                $this->editMessageKeyboard(
                    $project,
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

            $this->answerCallbackWithError($project, $callbackQueryId, 'Yopishda xatolik yuz berdi');
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
        Project $project,
        string $callbackQueryId,
        int $conversationId,
        mixed $chatId,
        mixed $messageId,
        array $from,
    ): JsonResponse {
        $conversation = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->with('project')
            ->find($conversationId);

        if ($conversation === null) {
            $this->answerCallbackWithError($project, $callbackQueryId, 'Suhbat topilmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        // Authorization: require Telegram user to be mapped to a super admin User
        $telegramUserId = $from['id'] ?? null;

        if ($telegramUserId === null) {
            $this->answerCallbackWithError($project, $callbackQueryId, 'Foydalanuvchi aniqlanmadi');

            return response()->json(['ok' => true, 'result' => true]);
        }

        $user = \App\Models\User::withoutGlobalScopes()
            ->where('telegram_user_id', (string) $telegramUserId)
            ->where('tenant_id', $project->tenant_id)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($user === null || !$user->isSuperAdmin()) {
            Log::warning('Telegram assign callback from non-super-admin user.', [
                'tenant_id' => $project->tenant_id,
                'telegram_user_id' => $telegramUserId,
                'user_found' => $user !== null,
                'is_super_admin' => $user?->is_super_admin ?? false,
            ]);

            $this->answerCallbackWithError($project, $callbackQueryId, 'Ruxsat berilmagan');

            return response()->json(['ok' => true, 'result' => true]);
        }

        try {
            $this->conversationService->assignConversation($conversation, $user);

            $this->telegramBotService->answerCallbackQuery(
                $project->telegram_bot_token,
                $callbackQueryId,
                'Suhbat sizga tayinlandi'
            );

            // Update the keyboard to show assigned state
            if ($chatId !== null && $messageId !== null && filled($project->telegram_bot_token) && $conversation->project) {
                $this->editMessageKeyboard(
                    $project,
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

            $this->answerCallbackWithError($project, $callbackQueryId, 'Tayinlashda xatolik yuz berdi');
        }

        return response()->json(['ok' => true, 'result' => true]);
    }

    /**
     * Handle an unknown callback action.
     */
    protected function handleUnknownCallback(
        Project $project,
        string $callbackQueryId,
        string $action,
    ): JsonResponse {
        try {
            $this->telegramBotService->answerCallbackQuery(
                $project->telegram_bot_token,
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
            'tenant_id' => $project->tenant_id,
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
            return '@' . trim($username);
        }

        // Priority 2: Use full name (first + last)
        if (is_string($firstName) && trim($firstName) !== '') {
            $name = trim($firstName);

            if (is_string($lastName) && trim($lastName) !== '') {
                $name .= ' ' . trim($lastName);
            }

            return $name;
        }

        // Fallback: Generic name
        return 'Telegram Admin';
    }

    /**
     * Answer a callback query with an error message.
     */
    protected function answerCallbackWithError(
        Project $project,
        string $callbackQueryId,
        string $errorMessage,
    ): void {
        try {
            if (filled($project->telegram_bot_token)) {
                $this->telegramBotService->answerCallbackQuery(
                    $project->telegram_bot_token,
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

    protected function editMessageKeyboard(
        Project $project,
        string $chatId,
        int $messageId,
        array $replyMarkup,
    ): void {
        if (blank($project->telegram_bot_token)) {
            return;
        }

        try {
            $this->telegramBotService->editMessageReplyMarkup(
                $project->telegram_bot_token,
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
}
