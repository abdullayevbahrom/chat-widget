<?php

namespace App\Listeners;

use App\Jobs\SendTelegramNotificationJob;
use App\Jobs\SetupTelegramWebhookJob;
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
        SendTelegramNotificationJob::class,
        SetupTelegramWebhookJob::class,
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
        // SAFETY: Use JSON payload only — avoid unserialize() to prevent RCE
        try {
            $payload = json_decode($job->getRawBody(), true);

            // Extract job class name safely from JSON
            if (isset($payload['displayName'])) {
                $context['job_class'] = class_basename($payload['displayName']);
            }

            // Laravel serialized command is in data.command — we extract class name only
            if (isset($payload['data']['commandName'])) {
                $context['job_class'] = class_basename($payload['data']['commandName']);
            }

            // If command is stored as serialized string, extract class name via regex (safe)
            if (isset($payload['data']['command']) && is_string($payload['data']['command'])) {
                $serializedCommand = $payload['data']['command'];
                // Extract class name from O: pattern without unserializing
                if (preg_match('/^O:\d+:"([^"]+)"/', $serializedCommand, $matches)) {
                    $context['job_class'] = class_basename($matches[1]);
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore payload extraction errors
        }

        return $context;
    }
}
