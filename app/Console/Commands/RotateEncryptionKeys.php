<?php

namespace App\Console\Commands;

use App\Models\TelegramBotSetting;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Rotate the application encryption key for all encrypted data.
 *
 * When APP_KEY changes, all data encrypted with Crypt::encryptString()
 * becomes unreadable. This command re-encrypts all stored secrets
 * with the new key.
 *
 * Usage: php artisan security:rotate-encryption --old-key=base64:xxxxx
 *
 * IMPORTANT: Run this immediately after changing APP_KEY.
 * Always backup the database before running this command.
 */
class RotateEncryptionKeys extends Command
{
    protected $signature = 'security:rotate-encryption
        {--old-key= : The previous APP_KEY value (required if APP_KEY was already changed)}
        {--dry-run : Preview what would be re-encrypted without making changes}
        {--force : Skip confirmation prompt}';

    protected $description = 'Re-encrypt all stored secrets with the current APP_KEY after key rotation';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('⚠️  This will re-encrypt all stored secrets. Have you backed up the database?', false)) {
                $this->error('Operation cancelled. Always backup before rotating encryption keys.');

                return self::FAILURE;
            }
        }

        $oldKey = $this->option('old-key');
        $isDryRun = $this->option('dry-run');

        // If old key not provided, try to use current APP_KEY as the "old" key
        // This handles the case where APP_KEY hasn't been changed yet
        if ($oldKey === null) {
            $this->warn('No --old-key provided. Assuming APP_KEY has NOT been changed yet.');
            $this->warn('If you have already changed APP_KEY, you must provide --old-key=your-old-key');

            if (! $this->confirm('Continue with current APP_KEY as the old key?', false)) {
                return self::FAILURE;
            }

            $oldKey = config('app.key');
        }

        $this->info('Starting encryption key rotation...');
        $this->line('Old key prefix: '.substr($oldKey, 0, 20).'...');
        $this->line('Current key prefix: '.substr((string) config('app.key'), 0, 20).'...');
        $this->line('Dry run: '.($isDryRun ? 'YES (no changes will be made)' : 'NO'));
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        // Re-encrypt Telegram bot tokens and webhook secrets
        $this->processTelegramBotSettings($oldKey, $isDryRun, $updated, $skipped, $failed);

        $this->newLine();
        $this->info('Rotation complete.');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped (empty/null)', $skipped],
                ['Failed', $failed],
            ]
        );

        if ($failed > 0) {
            $this->error("{$failed} records failed to re-encrypt. Check logs for details.");

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No changes were made.');
            $this->warn('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    /**
     * Process TelegramBotSetting records.
     */
    protected function processTelegramBotSettings(
        string $oldKey,
        bool $isDryRun,
        int &$updated,
        int &$skipped,
        int &$failed,
    ): void {
        $this->info('Processing TelegramBotSetting records...');

        $settings = TelegramBotSetting::query()->get();

        foreach ($settings as $setting) {
            $this->line("  Tenant {$setting->tenant_id}: ", output: false);

            // Re-encrypt bot token
            $botToken = $this->reencryptField(
                $oldKey,
                'bot_token_encrypted',
                $setting->bot_token_encrypted,
                $setting,
                $isDryRun
            );

            if ($botToken === 'skipped') {
                $skipped++;
                $this->line('skipped (no encrypted data)');

                continue;
            }

            if ($botToken === 'failed') {
                $failed++;
                $this->error('FAILED (could not decrypt with old key)');

                continue;
            }

            // Re-encrypt webhook secret
            $webhookSecret = $this->reencryptField(
                $oldKey,
                'webhook_secret_encrypted',
                $setting->webhook_secret_encrypted,
                $setting,
                $isDryRun
            );

            if ($webhookSecret === 'skipped') {
                $skipped++;
            }

            $updated++;
            $this->info('OK');
        }
    }

    /**
     * Re-encrypt a single encrypted field.
     *
     * @return string 'ok'|'skipped'|'failed'
     */
    protected function reencryptField(
        string $oldKey,
        string $field,
        ?string $encryptedValue,
        TelegramBotSetting $setting,
        bool $isDryRun,
    ): string {
        if ($encryptedValue === null || $encryptedValue === '') {
            return 'skipped';
        }

        try {
            // Temporarily set the old key to decrypt
            $currentKey = config('app.key');
            config(['app.key' => $oldKey]);

            // We need to create a new Crypt instance with the old key
            $crypt = new Encrypter(
                base64_decode(substr($oldKey, 7)), // Remove "base64:" prefix
                config('app.cipher', 'AES-256-CBC')
            );

            $decrypted = $crypt->decryptString($encryptedValue);

            // Restore current key
            config(['app.key' => $currentKey]);

            if ($isDryRun) {
                return 'ok';
            }

            // Re-encrypt with the new key (using the model's setter)
            if ($field === 'bot_token_encrypted') {
                $setting->bot_token = $decrypted;
            } elseif ($field === 'webhook_secret_encrypted') {
                $setting->webhook_secret = $decrypted;
            }

            $setting->saveQuietly();

            Log::info('Re-encrypted field after key rotation.', [
                'model' => TelegramBotSetting::class,
                'record_id' => $setting->id,
                'tenant_id' => $setting->tenant_id,
                'field' => $field,
            ]);

            return 'ok';
        } catch (\Throwable $e) {
            // Restore current key
            config(['app.key' => config('app.key')]);

            Log::error('Failed to re-encrypt field during key rotation.', [
                'model' => TelegramBotSetting::class,
                'record_id' => $setting->id,
                'tenant_id' => $setting->tenant_id,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
