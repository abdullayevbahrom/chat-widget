<?php

namespace App\Jobs;

use App\Models\TelegramBotSetting;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetupTelegramWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * The Telegram bot setting instance.
     */
    public TelegramBotSetting $setting;

    /**
     * Create a new job instance.
     */
    public function __construct(TelegramBotSetting $setting)
    {
        $this->setting = $setting;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $telegramService): void
    {
        // Restore tenant context for this job
        if ($this->setting->tenant_id !== null) {
            $tenant = \App\Models\Tenant::find($this->setting->tenant_id);

            if ($tenant !== null) {
                \App\Models\Tenant::setCurrent($tenant);
            }
        }

        $token = $this->setting->bot_token;

        if ($token === null) {
            throw new \Exception('Bot token is not set');
        }

        if ($this->setting->webhook_url === null) {
            throw new \Exception('Webhook URL is not set');
        }

        Log::info('Setting up Telegram webhook', [
            'setting_id' => $this->setting->id,
            'tenant_id' => $this->setting->tenant_id,
            'webhook_url' => $this->setting->webhook_url,
            'attempt' => $this->attempts(),
        ]);

        $result = $telegramService->setWebhook(
            $token,
            $this->setting->webhook_url,
            $this->setting->webhook_secret
        );

        $this->setting->last_webhook_status = 'set';
        $this->setting->save();

        Log::info('Telegram webhook set successfully', [
            'setting_id' => $this->setting->id,
            'tenant_id' => $this->setting->tenant_id,
            'result' => $result,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->setting->last_webhook_status = 'error';
        $this->setting->save();

        Log::error('Telegram webhook setup failed', [
            'setting_id' => $this->setting->id,
            'tenant_id' => $this->setting->tenant_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
