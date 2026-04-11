<?php

namespace App\Filament\Tenant\Pages;

use App\Jobs\SetupTelegramWebhookJob;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Services\TelegramBotService;
use Filament\Actions\Action;
use Filament\Forms\Components\Badge;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as SchemaForm;
use Filament\Schemas\Schema;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramBotSettings extends Page
{
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }

    public static function getNavigationLabel(): string
    {
        return 'Telegram Bot';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public function getView(): string
    {
        return 'filament.tenant.pages.telegram-bot-settings';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->tenant_id !== null;
    }

    public ?array $data = [];

    public ?string $botToken = null;

    public bool $showBotToken = false;

    public ?string $testMessageText = null;

    public ?string $botUsername = null;

    public ?string $botName = null;

    public ?string $webhookUrl = null;

    public ?string $webhookStatus = null;

    public bool $isActive = false;

    public ?int $settingId = null;

    /**
     * Cached tenant instance to avoid repeated lookups.
     */
    protected ?Tenant $cachedTenant = null;

    protected TelegramBotService $telegramService;

    public function boot(TelegramBotService $telegramService): void
    {
        $this->telegramService = $telegramService;
    }

    public function mount(): void
    {
        $setting = $this->getSetting();

        if ($setting) {
            $this->settingId = $setting->id;
            // Mask the token for display — show only last 4 characters
            $this->botToken = $this->maskToken($setting->bot_token);
            $this->showBotToken = false;
            $this->botUsername = $setting->bot_username;
            $this->botName = $setting->bot_name;
            $this->webhookUrl = $setting->webhook_url;
            $this->webhookStatus = $setting->last_webhook_status;
            $this->isActive = $setting->is_active;
        } else {
            $this->generateWebhookUrl();
        }
    }

    /**
     * Mask the bot token for display, showing only the last 4 characters.
     */
    protected function maskToken(?string $token): string
    {
        if ($token === null || $token === '') {
            return '';
        }

        $length = strlen($token);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4) . substr($token, -4);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bot Configuration')
                    ->description('Configure your Telegram bot token and webhook settings.')
                    ->schema([
                        TextInput::make('botToken')
                            ->label('Bot Token')
                            ->required()
                            ->password()
                            ->revealable()
                            ->placeholder('123456789:ABCdef-GHIjkl_MNOpqrSTUvwxYZ')
                            ->helperText('Get your token from @BotFather on Telegram.')
                            ->rules([
                                'required',
                                'string',
                                function ($attribute, $value, $fail) {
                                    if (!$this->telegramService->validateToken($value)) {
                                        $fail('The bot token format is invalid. Expected format: 123456789:ABCdef-GHIjkl.');
                                    }
                                },
                            ]),

                        TextInput::make('botUsername')
                            ->label('Bot Username')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('@username')
                            ->prefix('@')
                            ->helperText('Auto-filled after token validation.'),

                        TextInput::make('botName')
                            ->label('Bot Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Bot display name')
                            ->helperText('Auto-filled after token validation.'),
                    ])
                    ->columns(1),

                Section::make('Webhook Configuration')
                    ->description('Webhook URL for receiving Telegram updates.')
                    ->schema([
                        TextInput::make('webhookUrl')
                            ->label('Webhook URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('This URL will be registered with Telegram.'),

                        Badge::make('webhookStatus')
                            ->label('Webhook Status')
                            ->hidden(fn($state) => $state === null),

                        Toggle::make('isActive')
                            ->label('Activate Bot')
                            ->helperText('Enable or disable the bot integration.')
                            ->onColor('success')
                            ->offColor('danger'),
                    ])
                    ->columns(1),

                Section::make('Test Message')
                    ->description('Send a test message to verify the bot integration.')
                    ->schema([
                        TextInput::make('testMessageText')
                            ->label('Test Message')
                            ->placeholder('Hello from Widget!')
                            ->helperText('A test message will be sent to the bound chat.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('validate')
                ->label('Validate Token')
                ->action('validateToken')
                ->color('info')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Validate Bot Token')
                ->modalDescription('This will verify your token with Telegram and fetch bot information.')
                ->modalSubmitActionLabel('Validate'),

            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('success')
                ->icon('heroicon-o-document-text'),

            Action::make('setupWebhook')
                ->label('Setup Webhook')
                ->action('setupWebhook')
                ->color('primary')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->modalHeading('Setup Webhook')
                ->modalDescription('This will register your webhook URL with Telegram. The process runs in the background.')
                ->modalSubmitActionLabel('Setup Webhook')
                ->visible(fn() => $this->settingId !== null && $this->botUsername !== null),

            Action::make('deleteWebhook')
                ->label('Delete Webhook')
                ->action('deleteWebhook')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Delete Webhook')
                ->modalDescription('This will remove the webhook registration from Telegram.')
                ->modalSubmitActionLabel('Delete Webhook')
                ->visible(fn() => $this->settingId !== null && $this->webhookStatus === 'set'),

            Action::make('sendTestMessage')
                ->label('Send Test Message')
                ->action('sendTestMessage')
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Send Test Message')
                ->modalDescription('This will send a test message to the bound Telegram chat.')
                ->modalSubmitActionLabel('Send')
                ->visible(fn() => $this->settingId !== null && $this->botUsername !== null),

            Action::make('refreshWebhookStatus')
                ->label('Refresh Webhook Status')
                ->action('refreshWebhookStatus')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn() => $this->settingId !== null && $this->botUsername !== null),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return SchemaForm::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    public function validateToken(): void
    {
        $this->validate([
            'botToken' => ['required', 'string'],
        ]);

        // Resolve the actual token (may be masked)
        $actualToken = $this->resolveToken();

        if (!$this->telegramService->validateToken($actualToken)) {
            Notification::make()
                ->title('Invalid Token Format')
                ->body('The bot token format is invalid. Expected format: 123456789:ABCdef-GHIjkl.')
                ->danger()
                ->send();

            return;
        }

        // Rate limiting: max 3 validation attempts per 3 minutes
        // Use atomic Cache::add() to prevent race conditions
        $rateLimitKey = 'telegram_token_validation_' . hash('sha256', $actualToken);

        if (!Cache::add($rateLimitKey . '_locked', true, now()->addMinutes(3))) {
            Notification::make()
                ->title('Rate Limited')
                ->body('Too many validation attempts. Please wait a few moments and try again.')
                ->warning()
                ->send();

            return;
        }

        // Cache successful validation result for 5 minutes
        $cacheKey = 'telegram_bot_info_' . md5($actualToken);
        $cachedBotInfo = Cache::get($cacheKey);

        if ($cachedBotInfo !== null) {
            $this->botUsername = $cachedBotInfo['username'] ?? null;
            $this->botName = $cachedBotInfo['first_name'] ?? null;

            Notification::make()
                ->title('Token Validated (cached)')
                ->body("Bot: @{$this->botUsername} ({$this->botName})")
                ->success()
                ->send();

            return;
        }

        try {
            $botInfo = $this->telegramService->getBotInfo($actualToken);

            // Cache successful result for 5 minutes
            Cache::put($cacheKey, $botInfo, now()->addMinutes(5));

            $this->botUsername = $botInfo['username'] ?? null;
            $this->botName = $botInfo['first_name'] ?? null;

            Notification::make()
                ->title('Token Validated')
                ->body("Bot: @{$this->botUsername} ({$this->botName})")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Validation Failed')
                ->body('Unable to validate the token. Please check the token and try again.')
                ->danger()
                ->send();

            Log::error('Telegram token validation failed', [
                'token_prefix' => $this->sanitizeToken($actualToken),
                'error' => $e->getMessage(),
                'tenant_id' => $this->getTenant()?->id,
            ]);
        }
    }

    /**
     * Resolve the actual bot token from the form input.
     * If the token is masked, fetch the real token from the database.
     */
    protected function resolveToken(): string
    {
        // If the token contains mask characters, fetch the real token from DB
        if (str_contains($this->botToken, '•')) {
            $setting = $this->getSetting();

            if ($setting === null || $setting->bot_token === null) {
                throw new \Exception('No token available.');
            }

            return $setting->bot_token;
        }

        // If the token appears to be a masked password field value (dots), fetch from DB
        if (preg_match('/^\*+$/', $this->botToken ?? '')) {
            $setting = $this->getSetting();

            if ($setting === null || $setting->bot_token === null) {
                throw new \Exception('No token available.');
            }

            return $setting->bot_token;
        }

        return $this->botToken;
    }

    public function save(): void
    {
        $tenant = $this->getTenant();

        if ($tenant === null) {
            Notification::make()
                ->title('No tenant found')
                ->danger()
                ->send();

            return;
        }

        // Resolve the actual token (may be masked)
        try {
            $actualToken = $this->resolveToken();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Token Access Error')
                ->body('Unable to retrieve the stored token. Please contact support if this issue persists.')
                ->danger()
                ->send();

            return;
        }

        // Validate token format with regex before processing
        if (!$this->telegramService->validateToken($actualToken)) {
            Notification::make()
                ->title('Invalid Token Format')
                ->body('The bot token format is invalid. Expected format: 123456789:ABCdef-GHIjkl.')
                ->danger()
                ->send();

            return;
        }

        // Rate limiting: max 5 save attempts per 5 minutes per tenant
        // Use atomic Cache::add() to prevent race conditions
        $saveRateLimitKey = 'telegram_save_' . hash('sha256', 'tenant_' . $tenant->id);

        if (!Cache::add($saveRateLimitKey . '_locked', true, now()->addMinutes(5))) {
            Notification::make()
                ->title('Rate Limited')
                ->body('Too many save attempts. Please wait a few moments and try again.')
                ->warning()
                ->send();

            return;
        }

        $setting = $this->getSetting();

        if ($setting === null) {
            $setting = new TelegramBotSetting;
            $setting->tenant_id = $tenant->id;
            // Generate a secure webhook secret for new settings
            $setting->webhook_secret = bin2hex(random_bytes(32));
        }

        $setting->bot_token = $actualToken;
        $setting->bot_username = $this->botUsername;
        $setting->bot_name = $this->botName;
        $setting->webhook_url = $this->webhookUrl;
        $setting->is_active = $this->isActive;

        $setting->save();

        $this->settingId = $setting->id;

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();

        $this->logAudit('telegram_bot_settings_saved', $setting);
    }

    public function setupWebhook(): void
    {
        $setting = $this->getSetting();

        if ($setting === null) {
            Notification::make()
                ->title('No settings found')
                ->body('Please save your bot settings first.')
                ->danger()
                ->send();

            return;
        }

        SetupTelegramWebhookJob::dispatch($setting->id);

        $setting->last_webhook_status = 'pending';
        $setting->save();

        $this->webhookStatus = 'pending';

        Notification::make()
            ->title('Webhook Setup Queued')
            ->body('The webhook setup is running in the background. It may take a few moments.')
            ->info()
            ->send();

        $this->logAudit('telegram_webhook_setup_queued', $setting);
    }

    public function deleteWebhook(): void
    {
        $setting = $this->getSetting();

        if ($setting === null) {
            Notification::make()
                ->title('No settings found')
                ->danger()
                ->send();

            return;
        }

        try {
            $this->telegramService->deleteWebhook($setting->bot_token);

            $setting->last_webhook_status = null;
            $setting->save();

            $this->webhookStatus = null;

            Notification::make()
                ->title('Webhook Deleted')
                ->body('The webhook has been successfully removed from Telegram.')
                ->success()
                ->send();

            $this->logAudit('telegram_webhook_deleted', $setting);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Delete Webhook')
                ->body('An error occurred while deleting the webhook. Please try again later.')
                ->danger()
                ->send();

            Log::error('Telegram webhook deletion failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->getTenant()?->id,
                'setting_id' => $setting->id,
            ]);
        }
    }

    public function sendTestMessage(): void
    {
        $setting = $this->getSetting();

        if ($setting === null) {
            Notification::make()
                ->title('No settings found')
                ->body('Please save your bot settings first.')
                ->danger()
                ->send();

            return;
        }

        if ($setting->chat_id === null || $setting->chat_id === '') {
            Notification::make()
                ->title('No chat bound')
                ->body('No chat is bound to this bot yet. Send a message to the bot from Telegram first.')
                ->warning()
                ->send();

            return;
        }

        $messageText = $this->testMessageText ?: '👋 Test message from Widget! The integration is working correctly.';

        try {
            $this->telegramService->sendMessage(
                $setting->bot_token,
                $setting->chat_id,
                $messageText
            );

            Notification::make()
                ->title('Test Message Sent')
                ->body('The test message has been delivered to the bound chat.')
                ->success()
                ->send();

            $this->logAudit('telegram_test_message_sent', $setting);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Send Test Message')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('Telegram test message send failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->getTenant()?->id,
                'setting_id' => $setting->id,
                'chat_id' => $setting->chat_id,
            ]);
        }
    }

    public function refreshWebhookStatus(): void
    {
        $setting = $this->getSetting();

        if ($setting === null) {
            Notification::make()
                ->title('No settings found')
                ->danger()
                ->send();

            return;
        }

        try {
            $status = $this->telegramService->checkWebhookStatus($setting->bot_token);

            $this->webhookStatus = $status['status'];

            // Update the database with the latest webhook info
            $setting->last_webhook_status = $status['status'];
            $setting->save();

            $statusMessage = match ($status['status']) {
                'active' => 'Webhook is active and working.',
                'error' => "Webhook error: {$status['last_error_message']}",
                'not_set' => 'No webhook is configured.',
                default => 'Unknown status.',
            };

            Notification::make()
                        ->title('Webhook Status: ' . ucfirst($status['status']))
                        ->body($statusMessage)
                ->{$status['status'] === 'active' ? 'success' : ($status['status'] === 'error' ? 'danger' : 'info')}()
                    ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Check Webhook Status')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('Telegram webhook status check failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->getTenant()?->id,
                'setting_id' => $setting->id,
            ]);
        }
    }

    protected function getSetting(): ?TelegramBotSetting
    {
        $tenant = $this->getTenant();

        if ($tenant === null) {
            return null;
        }

        return TelegramBotSetting::where('tenant_id', $tenant->id)->first();
    }

    protected function getTenant(): ?Tenant
    {
        if ($this->cachedTenant !== null) {
            return $this->cachedTenant;
        }

        $user = Auth::user();

        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        $this->cachedTenant = $user->tenant;

        return $this->cachedTenant;
    }

    protected function generateWebhookUrl(): void
    {
        $tenant = $this->getTenant();

        if ($tenant) {
            $this->webhookUrl = route('telegram.webhook', ['tenantSlug' => $tenant->slug]);
        }
    }

    protected function logAudit(string $event, TelegramBotSetting $setting): void
    {
        logger()->info("Audit: {$event}", [
            'setting_id' => $setting->id,
            'tenant_id' => $setting->tenant_id,
            'updated_by' => Auth::id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sanitize a bot token for safe logging.
     * Replaces the middle portion of the token with asterisks.
     * Works for both long and short tokens.
     */
    protected function sanitizeToken(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return $token;
        }

        // Try to mask the middle portion for longer tokens
        $sanitized = preg_replace('/^(\d+:).{4,}([A-Za-z0-9_-]{4})$/', '$1****$2', $token);

        // If regex didn't match (short token), fall back to simple masking
        if ($sanitized === $token) {
            $parts = explode(':', $token, 2);
            if (count($parts) === 2) {
                $prefix = $parts[0];
                $suffix = $parts[1];
                $suffixLength = strlen($suffix);

                if ($suffixLength > 4) {
                    $maskedSuffix = substr($suffix, 0, 2) . '****' . substr($suffix, -2);
                } elseif ($suffixLength > 0) {
                    $maskedSuffix = '****';
                } else {
                    $maskedSuffix = '';
                }

                return $prefix . ':' . $maskedSuffix;
            }
        }

        return $sanitized;
    }
}
