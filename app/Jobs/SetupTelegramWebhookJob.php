<?php

namespace App\Jobs;

use App\Exceptions\TelegramApiException;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;

class SetupTelegramWebhookJob extends TenantAwareJob
{
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
     * The Telegram bot setting ID.
     */
    public int $settingId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $settingId)
    {
        parent::__construct();
        $this->settingId = $settingId;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $telegramService): void
    {
        $this->withTenantContext(function () use ($telegramService): void {
            $setting = TelegramBotSetting::withoutGlobalScopes()
                ->where('id', $this->settingId)
                ->first();

            if ($setting === null) {
                Log::warning('Telegram bot setting not found for webhook setup', [
                    'channel' => 'jobs',
                    'job' => self::class,
                    'setting_id' => $this->settingId,
                ]);

                return;
            }

            $token = $setting->bot_token;

            if ($token === null) {
                throw new \Exception('Bot token is not set');
            }

            if ($setting->webhook_url === null) {
                throw new \Exception('Webhook URL is not set');
            }

            Log::info('Setting up Telegram webhook', [
                'channel' => 'jobs',
                'job' => self::class,
                'setting_id' => $setting->id,
                'tenant_id' => $setting->tenant_id,
                'webhook_url' => $setting->webhook_url,
                'attempt' => $this->attempts(),
            ]);

            $result = $telegramService->setWebhook(
                $token,
                $setting->webhook_url,
                $setting->webhook_secret
            );

            $setting->last_webhook_status = 'set';
            $setting->last_webhook_error = null;
            $setting->last_webhook_error_at = null;
            $setting->save();

            Log::info('Telegram webhook set successfully', [
                'channel' => 'jobs',
                'job' => self::class,
                'setting_id' => $setting->id,
                'tenant_id' => $setting->tenant_id,
                'result' => $result,
            ]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $setting = Tenant::withoutTenantContext(fn() => TelegramBotSetting::withoutGlobalScopes()->find($this->settingId));

        if ($setting === null) {
            Log::error('Telegram webhook setup failed (setting not found)', [
                'channel' => 'jobs',
                'job' => self::class,
                'setting_id' => $this->settingId,
                'error' => $exception->getMessage(),
                'error_type' => get_class($exception),
                'attempts' => $this->attempts(),
            ]);

            return;
        }

        $setting->last_webhook_status = 'error';
        $setting->last_webhook_error = [
            'message' => $exception->getMessage(),
            'type' => get_class($exception),
            'code' => $exception instanceof TelegramApiException ? $exception->errorCode : $exception->getCode(),
            'attempts' => $this->attempts(),
        ];
        $setting->last_webhook_error_at = now();
        $setting->save();

        Log::error('Telegram webhook setup failed', [
            'channel' => 'jobs',
            'job' => self::class,
            'setting_id' => $setting->id,
            'tenant_id' => $setting->tenant_id,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'attempts' => $this->attempts(),
        ]);
    }
}
