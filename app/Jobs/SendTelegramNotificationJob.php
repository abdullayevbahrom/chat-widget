<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\TelegramBotSetting;
use App\Services\TelegramBotService;
use App\Services\TelegramInlineKeyboard;
use App\Services\TelegramMessageFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a formatted Telegram notification with inline keyboard
 * for an incoming widget message. Runs asynchronously via queue.
 */
class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Backoff times in seconds for exponential retry.
     *
     * @var array<int>
     */
    public $backoff = [10, 30, 60];

    /**
     * Tenant ID for loading bot settings.
     */
    public int $tenantId;

    /**
     * Project ID for loading project details.
     */
    public int $projectId;

    /**
     * Message ID for loading the widget message.
     */
    public int $messageId;

    /**
     * Conversation ID for loading conversation and building keyboard.
     */
    public int $conversationId;

    /**
     * Visitor metadata (name, email, etc.).
     *
     * @var array<string, mixed>
     */
    public array $visitorData;

    /**
     * Create a new job instance.
     *
     * @param  int  $tenantId  The tenant ID
     * @param  int  $projectId  The project ID
     * @param  int  $messageId  The message ID
     * @param  int  $conversationId  The conversation ID
     * @param  array<string, mixed>  $visitorData  Visitor metadata
     */
    public function __construct(int $tenantId, int $projectId, int $messageId, int $conversationId, array $visitorData = [])
    {
        $this->tenantId = $tenantId;
        $this->projectId = $projectId;
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
        $this->visitorData = $visitorData;
    }

    /**
     * Execute the job.
     *
     * Sends a formatted notification to the tenant's Telegram bot chat
     * with inline keyboard buttons for quick admin actions.
     */
    public function handle(TelegramBotService $telegramBotService): void
    {
        // Restore tenant context for this job
        if ($this->tenantId !== null) {
            $tenant = \App\Models\Tenant::find($this->tenantId);

            if ($tenant !== null) {
                \App\Models\Tenant::setCurrent($tenant);
            }
        }

        $telegramSetting = TelegramBotSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->first();

        if ($telegramSetting === null) {
            Log::warning('Skipping Telegram notification: bot setting not found.', [
                'tenant_id' => $this->tenantId,
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $token = $telegramSetting->bot_token;
        $chatId = $telegramSetting->chat_id;

        if (blank($token) || blank($chatId)) {
            Log::info('Skipping Telegram notification: bot token or chat_id is not configured.', [
                'tenant_id' => $this->tenantId,
                'has_token' => filled($token),
                'has_chat_id' => filled($chatId),
            ]);

            return;
        }

        $message = Message::withoutGlobalScopes()
            ->with('conversation')
            ->find($this->messageId);

        if ($message === null) {
            Log::warning('Skipping Telegram notification: message not found.', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $conversation = $message->conversation;

        if ($conversation === null) {
            Log::warning('Skipping Telegram notification: conversation not found.', [
                'message_id' => $this->messageId,
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        $project = Project::withoutGlobalScopes()->find($this->projectId);

        if ($project === null) {
            Log::warning('Skipping Telegram notification: project not found.', [
                'project_id' => $this->projectId,
            ]);

            return;
        }

        // Format the message using MarkdownV2
        $formatted = TelegramMessageFormatter::format($message, $project, $this->visitorData);

        // Build inline keyboard
        $replyMarkup = TelegramInlineKeyboard::buildForConversation($conversation, $project);

        $response = $telegramBotService->sendMessage(
            $token,
            $chatId,
            $formatted['telegram_text'],
            $formatted['parse_mode'],
            $replyMarkup,
        );

        $telegramMessageId = $response['result']['message_id'] ?? null;

        if ($telegramMessageId !== null) {
            $message->updateQuietly([
                'telegram_message_id' => $telegramMessageId,
            ]);
        }

        Log::info('Delivered Telegram notification with inline keyboard.', [
            'tenant_id' => $this->tenantId,
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'telegram_message_id' => $telegramMessageId,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Logs the failure but does not re-throw — widget responses
     * should not be blocked by Telegram delivery issues.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Telegram notification job failed.', [
            'tenant_id' => $this->tenantId,
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
