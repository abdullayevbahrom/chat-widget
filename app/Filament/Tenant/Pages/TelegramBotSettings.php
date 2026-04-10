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
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TelegramBotSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string $view = 'filament.tenant.pages.telegram-bot-settings';

    protected static ?string $navigationLabel = 'Telegram Bot';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public ?string $botToken = null;

    public ?string $botUsername = null;

    public ?string $botName = null;

    public ?string $webhookUrl = null;

    public ?string $webhookStatus = null;

    public bool $isActive = false;

    public ?int $settingId = null;

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

        return str_repeat('•', $length - 4).substr($token, -4);
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
                            ->placeholder('123456789:ABCdef-GHIjkl_MNOpqrSTUvwxYZ')
                            ->helperText('Get your token from @BotFather on Telegram.')
                            ->rules([
                                'required',
                                'string',
                                function ($attribute, $value, $fail) {
                                    if (! $this->telegramService->validateToken($value)) {
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
                            ->hidden(fn ($state) => $state === null),

                        Toggle::make('isActive')
                            ->label('Activate Bot')
                            ->helperText('Enable or disable the bot integration.')
                            ->onColor('success')
                            ->offColor('danger'),
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
                ->visible(fn () => $this->settingId !== null && $this->botUsername !== null),

            Action::make('deleteWebhook')
                ->label('Delete Webhook')
                ->action('deleteWebhook')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Delete Webhook')
                ->modalDescription('This will remove the webhook registration from Telegram.')
                ->modalSubmitActionLabel('Delete Webhook')
                ->visible(fn () => $this->settingId !== null && $this->webhookStatus === 'set'),
        ];
    }

    public function validateToken(): void
    {
        $this->validate([
            'botToken' => ['required', 'string'],
        ]);

        // Resolve the actual token (may be masked)
        $actualToken = $this->resolveToken();

        if (! $this->telegramService->validateToken($actualToken)) {
            Notification::make()
                ->title('Invalid Token Format')
                ->body('The bot token format is invalid. Expected format: 123456789:ABCdef-GHIjkl.')
                ->danger()
                ->send();

            return;
        }

        try {
            $botInfo = $this->telegramService->getBotInfo($actualToken);

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
        $actualToken = $this->resolveToken();

        // Validate token format with regex before processing
        if (! $this->telegramService->validateToken($actualToken)) {
            Notification::make()
                ->title('Invalid Token Format')
                ->body('The bot token format is invalid. Expected format: 123456789:ABCdef-GHIjkl.')
                ->danger()
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

        SetupTelegramWebhookJob::dispatch($setting);

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
        $user = Auth::user();

        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        return $user->tenant;
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
}
