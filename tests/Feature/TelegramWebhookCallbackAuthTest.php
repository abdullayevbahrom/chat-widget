<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\TelegramBotService;
use App\Services\TelegramInlineKeyboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TelegramWebhookCallbackAuthTest extends TestCase
{
    use RefreshDatabase;

    protected TelegramBotSetting $setting;
    protected Tenant $tenant;
    protected Project $project;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure APP_KEY is set for HMAC signing
        Config::set('app.key', 'base64:1uSGMthiynjqxVR4Ez64SlGR/JnvH7FqGkWXwE330yw=');

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => Conversation::STATUS_OPEN,
        ]);

        $this->setting = TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456:ABCdef',
            'webhook_secret' => bin2hex(random_bytes(32)),
            'telegram_admin_ids' => ['111222333'],
        ]);
    }

    /**
     * Test Fix 10: HMAC signature tekshirilmagan callback qaytarilishi kerak.
     */
    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $text === "Noto'g'ri imzo";
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $invalidSignature = 'invalidsig';
        $callbackData = "reply:{$this->tenant->id}:{$this->conversation->id}:{$invalidSignature}";

        $payload = [
            'update_id' => 100001,
            'callback_query' => [
                'id' => 'callback_1',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'admin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 50,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();
    }

    /**
     * Test Fix 10: callback_data format mismatch — noto'g'ri format rad etilishi kerak.
     */
    public function test_callback_with_malformed_data_is_rejected(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $text === "Noto'g'ri so'rov formati" || $text === "Noto'g'ri imzo";
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        // Old format: action:conversationId (without tenant_id and signature)
        $callbackData = "reply:{$this->conversation->id}";

        $payload = [
            'update_id' => 100002,
            'callback_query' => [
                'id' => 'callback_2',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'admin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 51,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();
    }

    /**
     * Test Fix 10: Tenant mismatch — boshqa tenant callback'i rad etilishi kerak.
     */
    public function test_callback_with_tenant_mismatch_is_rejected(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $text === "Noto'g'ri tenant";
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        // Use wrong tenant_id
        $wrongTenantId = 99999;
        $signature = TelegramInlineKeyboard::signCallbackData($wrongTenantId, $this->conversation->id);
        $callbackData = "reply:{$wrongTenantId}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100003,
            'callback_query' => [
                'id' => 'callback_3',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'admin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 52,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();
    }

    /**
     * Test Fix 11: telegram_admin_ids bo'sh bo'lsa callback rad etilishi kerak.
     */
    public function test_callback_is_rejected_when_admin_ids_empty(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        // Clear admin IDs
        $this->setting->update(['telegram_admin_ids' => []]);

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $showAlert === true && $text === 'Ruxsat berilmagan';
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $signature = TelegramInlineKeyboard::signCallbackData($this->tenant->id, $this->conversation->id);
        $callbackData = "reply:{$this->tenant->id}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100004,
            'callback_query' => [
                'id' => 'callback_4',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'admin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 53,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();
    }

    /**
     * Test Fix 12: handleCallbackAssign da isSuperAdmin validatsiyasi.
     * Super admin bo'lmagan user tayinlay olmasligi kerak.
     */
    public function test_assign_callback_rejected_for_non_super_admin(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        // Create a non-super-admin user mapped to this Telegram user
        $nonAdminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telegram_user_id' => '111222333',
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $text === 'Ruxsat berilmagan';
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $signature = TelegramInlineKeyboard::signCallbackData($this->tenant->id, $this->conversation->id);
        $callbackData = "assign:{$this->tenant->id}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100005,
            'callback_query' => [
                'id' => 'callback_5',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'nonadmin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 54,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();

        // Conversation should NOT be assigned
        $this->conversation->refresh();
        $this->assertNull($this->conversation->assigned_to);
    }

    /**
     * Test Fix 12: Super admin user conversation ga tayinlanishi kerak.
     */
    public function test_assign_callback_succeeds_for_super_admin(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $superAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telegram_user_id' => '111222333',
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn ($token, $callbackId, $text) => $text === 'Suhbat sizga tayinlandi')
            ->andReturn(true);
        $telegramBotService->shouldReceive('editMessageReplyMarkup')
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $signature = TelegramInlineKeyboard::signCallbackData($this->tenant->id, $this->conversation->id);
        $callbackData = "assign:{$this->tenant->id}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100006,
            'callback_query' => [
                'id' => 'callback_6',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'superadmin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 55,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();

        // Conversation should be assigned to the super admin
        $this->conversation->refresh();
        $this->assertEquals($superAdmin->id, $this->conversation->assigned_to);
    }

    /**
     * Test Fix 12: Mapping mavjud bo'lmagan Telegram user tayinlay olmasligi kerak.
     */
    public function test_assign_callback_rejected_without_user_mapping(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        // No user with this telegram_user_id exists

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function ($token, $callbackId, $text, $showAlert) {
                return $text === 'Ruxsat berilmagan';
            })
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $signature = TelegramInlineKeyboard::signCallbackData($this->tenant->id, $this->conversation->id);
        $callbackData = "assign:{$this->tenant->id}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100007,
            'callback_query' => [
                'id' => 'callback_7',
                'data' => $callbackData,
                'from' => ['id' => '999888777', 'username' => 'unknown'],
                'message' => [
                    'chat' => ['id' => '999888777'],
                    'message_id' => 56,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();

        $this->conversation->refresh();
        $this->assertNull($this->conversation->assigned_to);
    }

    /**
     * Test Fix 10: Valid callback to'g'ri parse qilinishi kerak.
     */
    public function test_valid_callback_is_processed_correctly(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $telegramBotService = Mockery::mock(TelegramBotService::class);
        $telegramBotService->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn ($token, $callbackId) => true)
            ->andReturn(true);

        $this->app->instance(TelegramBotService::class, $telegramBotService);

        $signature = TelegramInlineKeyboard::signCallbackData($this->tenant->id, $this->conversation->id);
        $callbackData = "reply:{$this->tenant->id}:{$this->conversation->id}:{$signature}";

        $payload = [
            'update_id' => 100008,
            'callback_query' => [
                'id' => 'callback_8',
                'data' => $callbackData,
                'from' => ['id' => '111222333', 'username' => 'admin'],
                'message' => [
                    'chat' => ['id' => '111222333'],
                    'message_id' => 57,
                ],
            ],
        ];

        $response = $this->postJson("/api/telegram/webhook/{$this->tenant->slug}", $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => $this->setting->webhook_secret,
        ]);

        $response->assertOk();
    }
}
