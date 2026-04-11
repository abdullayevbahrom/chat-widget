<?php

namespace App\Jobs;

use App\Exceptions\TelegramApiException;
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
     * Retry-After seconds from rate limit (set during execution).
     */
    public ?int $rateLimitRetryAfter = null;

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
     * Get the backoff array, using rate limit retry-after if available.
     */
    public function backoff(): array
    {
        // If we have a rate limit retry-after, use it instead of default backoff
        if ($this->rateLimitRetryAfter !== null && $this->rateLimitRetryAfter > 0) {
            return [$this->rateLimitRetryAfter, $this->rateLimitRetryAfter * 2, $this->rateLimitRetryAfter * 4];
        }

        return $this->backoff;
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
                'channel' => 'jobs',
                'job' => self::class,
                'tenant_id' => $this->tenantId,
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $token = $telegramSetting->bot_token;
        $chatId = $telegramSetting->chat_id;

        if (blank($token) || blank($chatId)) {
            Log::info('Skipping Telegram notification: bot token or chat_id is not configured.', [
                'channel' => 'jobs',
                'job' => self::class,
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
                'channel' => 'jobs',
                'job' => self::class,
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $conversation = $message->conversation;

        if ($conversation === null) {
            Log::warning('Skipping Telegram notification: conversation not found.', [
                'channel' => 'jobs',
                'job' => self::class,
                'message_id' => $this->messageId,
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        $project = Project::withoutGlobalScopes()->find($this->projectId);

        if ($project === null) {
            Log::warning('Skipping Telegram notification: project not found.', [
                'channel' => 'jobs',
                'job' => self::class,
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
            'channel' => 'jobs',
            'job' => self::class,
            'tenant_id' => $this->tenantId,
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'telegram_message_id' => $telegramMessageId,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Logs the failure with structured context based on exception type.
     */
    public function failed(\Throwable $exception): void
    {
        if ($exception instanceof TelegramApiException) {
            // Non-retryable Telegram API errors
            if (! $exception->isRetryable) {
                Log::error('Telegram notification job failed (non-retryable API error).', [
                    'channel' => 'jobs',
                    'job' => self::class,
                    'tenant_id' => $this->tenantId,
                    'message_id' => $this->messageId,
                    'conversation_id' => $this->conversationId,
                    'error' => $exception->getMessage(),
                    'error_code' => $exception->errorCode,
                    'error_description' => $exception->errorDescription,
                    'error_type' => $exception->errorCode,
                    'is_retryable' => $exception->isRetryable,
                    'attempts' => $this->attempts(),
                ]);

                return;
            }

            // Retryable errors (rate limit, server error)
            Log::warning('Telegram notification job failed (retryable API error).', [
                'channel' => 'jobs',
                'job' => self::class,
                'tenant_id' => $this->tenantId,
                'message_id' => $this->messageId,
                'conversation_id' => $this->conversationId,
                'error' => $exception->getMessage(),
                'error_code' => $exception->errorCode,
                'retry_after_seconds' => $exception->retryAfterSeconds,
                'is_retryable' => $exception->isRetryable,
                'attempts' => $this->attempts(),
            ]);

            // Store rate limit retry-after for next attempt
            if ($exception->retryAfterSeconds !== null) {
                $this->rateLimitRetryAfter = $exception->retryAfterSeconds;
            }

            return;
        }

        // Generic errors (network, etc.)
        Log::error('Telegram notification job failed.', [
            'channel' => 'jobs',
            'job' => self::class,
            'tenant_id' => $this->tenantId,
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'attempts' => $this->attempts(),
        ]);
    }
}
