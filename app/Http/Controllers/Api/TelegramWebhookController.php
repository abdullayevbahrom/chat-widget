<?php

namespace App\Http\Controllers\Api;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Services\MessageAttachmentService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected MessageAttachmentService $messageAttachmentService,
    ) {}

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $clientIp = $request->ip();
        $forwardedFor = $request->header('X-Forwarded-For');
        $realIp = $request->header('X-Real-IP');

        if ($forwardedFor !== null || $realIp !== null) {
            Log::info('Telegram webhook with proxy headers.', [
                'client_ip' => $clientIp,
                'x_forwarded_for' => $forwardedFor,
                'x_real_ip' => $realIp,
                'tenant_slug' => $tenantSlug,
            ]);
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            Log::warning('Telegram webhook received for unknown tenant.', [
                'tenant_slug' => $tenantSlug,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Tenant not found'], 404);
        }

        $setting = TelegramBotSetting::where('tenant_id', $tenant->id)->first();

        if ($setting === null) {
            Log::warning('Telegram webhook received for tenant without bot settings.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenantSlug,
            ]);

            return response()->json(['ok' => false, 'error' => 'Bot not configured'], 404);
        }

        if ($setting->webhook_secret !== null && $secretToken !== null) {
            if (! hash_equals($setting->webhook_secret, $secretToken)) {
                Log::warning('Telegram webhook received with invalid secret token.', [
                    'tenant_id' => $tenant->id,
                    'setting_id' => $setting->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
        } elseif ($setting->webhook_secret !== null && $secretToken === null) {
            Log::warning('Telegram webhook received without secret token header.', [
                'tenant_id' => $tenant->id,
                'setting_id' => $setting->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (! $setting->is_active) {
            Log::info('Telegram webhook received for inactive bot.', [
                'tenant_id' => $tenant->id,
                'setting_id' => $setting->id,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $payload = $request->all();

        Log::info('Telegram webhook received.', [
            'tenant_id' => $tenant->id,
            'setting_id' => $setting->id,
            'update_id' => $payload['update_id'] ?? null,
            'has_message' => isset($payload['message']),
            'has_callback_query' => isset($payload['callback_query']),
        ]);

        if (! isset($payload['message']) || ! is_array($payload['message'])) {
            return response()->json(['ok' => true, 'result' => true]);
        }

        $messagePayload = $payload['message'];
        $chatId = isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null;

        if ($chatId === null || $chatId === '') {
            Log::warning('Telegram webhook message is missing chat context.', [
                'tenant_id' => $tenant->id,
                'update_id' => $payload['update_id'] ?? null,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $this->syncChatBinding($setting, $chatId);

        if ($setting->chat_id !== $chatId) {
            Log::warning('Ignoring Telegram message from an unexpected chat.', [
                'tenant_id' => $tenant->id,
                'expected_chat_id' => $setting->chat_id,
                'actual_chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $replyToTelegramId = $messagePayload['reply_to_message']['message_id'] ?? null;

        if (! is_int($replyToTelegramId)) {
            Log::info('Ignoring Telegram message because it is not a reply to a widget notification.', [
                'tenant_id' => $tenant->id,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
                'chat_id' => $chatId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $sourceMessage = Message::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('telegram_message_id', $replyToTelegramId)
            ->first();

        if ($sourceMessage === null) {
            Log::warning('Ignoring Telegram reply because the original widget message could not be found.', [
                'tenant_id' => $tenant->id,
                'reply_to_telegram_message_id' => $replyToTelegramId,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $conversation = Conversation::withoutGlobalScopes()->find($sourceMessage->conversation_id);
        $project = $conversation?->project()->withoutGlobalScopes()->first();

        if ($conversation === null || $project === null) {
            Log::warning('Ignoring Telegram reply because conversation context is incomplete.', [
                'tenant_id' => $tenant->id,
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
        $body = $this->resolveTelegramBody($messagePayload);

        if ($body === null && $attachments === []) {
            Log::info('Ignoring Telegram reply because it contains neither text nor attachments.', [
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'telegram_message_id' => $messagePayload['message_id'] ?? null,
            ]);

            return response()->json(['ok' => true, 'result' => true]);
        }

        $adminMessage = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
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
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'message_id' => $adminMessage->id,
            'telegram_message_id' => $messagePayload['message_id'] ?? null,
            'attachment_count' => count($attachments),
        ]);

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
     * @param  array<string, mixed>  $telegramMessage
     */
    protected function resolveTelegramBody(array $telegramMessage): ?string
    {
        foreach (['text', 'caption'] as $field) {
            $value = $telegramMessage[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    protected function resolveMessageType(array $attachments): string
    {
        if ($attachments === []) {
            return Message::TYPE_TEXT;
        }

        $hasOnlyImages = collect($attachments)->every(
            fn (array $attachment): bool => str_starts_with((string) ($attachment['mime_type'] ?? ''), 'image/')
        );

        return $hasOnlyImages ? Message::TYPE_IMAGE : Message::TYPE_FILE;
    }
}
