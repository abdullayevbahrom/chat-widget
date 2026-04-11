<?php

namespace App\Listeners;

use App\Services\ErrorNotificationService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Listener for failed queue jobs.
 *
 * Sends notifications to admins when important jobs fail
 * after all retry attempts.
 */
class SendFailedJobNotification
{
    /**
     * Important job classes that should trigger admin notifications.
     */
    protected const IMPORTANT_JOBS = [
        \App\Jobs\SendTelegramNotificationJob::class,
        \App\Jobs\SetupTelegramWebhookJob::class,
    ];

    public function __construct(
        protected ErrorNotificationService $errorNotificationService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();

        // Only notify for important jobs
        if (! in_array($jobName, self::IMPORTANT_JOBS, true)) {
            return;
        }

        $exception = $event->exception;
        $attempts = $event->job->attempts();

        Log::error('Important job failed', [
            'channel' => 'jobs',
            'job' => $jobName,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'attempts' => $attempts,
            'uuid' => $event->job->uuid()?->toString(),
        ]);

        // Build notification message
        $message = "⚠️ Job muvaffaqiyatsiz: {$jobName}";

        // Extract context from job payload
        $context = $this->extractContext($event->job, $exception);

        $this->errorNotificationService->notifyAdmins($message, $context);
    }

    /**
     * Extract relevant context from the failed job.
     */
    protected function extractContext($job, \Throwable $exception): array
    {
        $context = [
            'error' => $exception->getMessage(),
            'attempts' => $job->attempts(),
            'timestamp' => now()->toISOString(),
        ];

        // Try to extract tenant_id and other metadata from job payload
        try {
            $payload = json_decode($job->getRawBody(), true);

            if (isset($payload['data']['command'])) {
                $command = unserialize($payload['data']['command']);

                if (isset($command->tenantId)) {
                    $context['tenant_id'] = $command->tenantId;
                }

                if (isset($command->messageId)) {
                    $context['message_id'] = $command->messageId;
                }

                if (isset($command->conversationId)) {
                    $context['conversation_id'] = $command->conversationId;
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore payload extraction errors
        }

        return $context;
    }
}
