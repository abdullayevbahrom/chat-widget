<?php

namespace Tests\Feature;

use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Tenant::setCurrent($this->tenant);
    }

    protected function tearDown(): void
    {
        Tenant::clearCurrent();
        parent::tearDown();
    }

    #[Test]
    public function guest_cannot_access_telegram_settings_page(): void
    {
        $this->get('/dashboard/telegram-bot-settings')
            ->assertRedirect('/auth/login');
    }

    #[Test]
    public function user_can_view_telegram_settings_page(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/telegram-bot-settings')
            ->assertOk()
            ->assertSee('Telegram Bot Settings')
            ->assertSee('Bot Token')
            ->assertSee('Chat ID')
            ->assertSee('Webhook URL')
            ->assertSee('Send Test');
    }

    #[Test]
    public function settings_page_shows_masked_bot_token(): void
    {
        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'bot_username' => '@testbot',
        ]);

        $response = $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/telegram-bot-settings');

        // Should not show the full token
        $response->assertDontSee('123456789:ABCdefGHIjklMNOpqrsTUVwxyz');
        // But should see something (masked)
        $response->assertSee('bot_token');
    }

    #[Test]
    public function user_can_update_telegram_settings(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/telegram-bot-settings', [
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
                'chat_id' => '-1001234567890',
                'webhook_url' => 'https://example.com/webhook/telegram',
                'is_active' => 1,
            ])
            ->assertRedirect('/dashboard/telegram-bot-settings')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('telegram_bot_settings', [
            'tenant_id' => $this->tenant->id,
            'chat_id' => '-1001234567890',
            'webhook_url' => 'https://example.com/webhook/telegram',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function bot_token_is_encrypted_in_database(): void
    {
        $plainToken = '123456789:ABCdefGHIjklMNOpqrsTUVwxyz';

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/telegram-bot-settings', [
                'bot_token' => $plainToken,
                'chat_id' => '-1001234567890',
            ]);

        // The encrypted value should not match the plain text
        $this->assertDatabaseHas('telegram_bot_settings', [
            'tenant_id' => $this->tenant->id,
        ]);

        $setting = TelegramBotSetting::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotEquals($plainToken, $setting->bot_token_encrypted);
    }

    #[Test]
    public function user_can_update_settings_without_changing_token(): void
    {
        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'chat_id' => 'old-chat-id',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/telegram-bot-settings', [
                'bot_token' => '***********************xyz', // masked token
                'chat_id' => '-1009998887776',
                'is_active' => 1,
            ])
            ->assertRedirect('/dashboard/telegram-bot-settings');

        $setting = TelegramBotSetting::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('-1009998887776', $setting->chat_id);
        // Original token should be preserved
        $this->assertEquals('123456789:ABCdefGHIjklMNOpqrsTUVwxyz', $setting->bot_token);
    }

    #[Test]
    public function webhook_url_must_be_valid_url(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/telegram-bot-settings', [
                'webhook_url' => 'not-a-valid-url',
            ])
            ->assertSessionHasErrors('webhook_url');
    }

    #[Test]
    public function send_test_message_success(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123],
            ], 200),
        ]);

        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'chat_id' => '-1001234567890',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->postJson('/dashboard/telegram-bot-settings/test-message')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test message sent successfully!',
            ]);
    }

    #[Test]
    public function send_test_message_fails_without_bot_token(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->postJson('/dashboard/telegram-bot-settings/test-message')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Bot token is not configured.',
            ]);
    }

    #[Test]
    public function send_test_message_fails_without_chat_id(): void
    {
        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->postJson('/dashboard/telegram-bot-settings/test-message')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Chat ID is not configured.',
            ]);
    }

    #[Test]
    public function send_test_message_handles_api_error(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => false,
                'description' => 'chat not found',
            ], 400),
        ]);

        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'chat_id' => '-1001234567890',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->postJson('/dashboard/telegram-bot-settings/test-message')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'chat not found',
            ]);
    }

    #[Test]
    public function delete_webhook_success(): void
    {
        Http::fake([
            'api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'webhook_url' => 'https://example.com/webhook/telegram',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/telegram-bot-settings/delete-webhook')
            ->assertRedirect('/dashboard/telegram-bot-settings')
            ->assertSessionHas('success', 'Webhook deleted successfully.');

        $setting = TelegramBotSetting::where('tenant_id', $this->tenant->id)->first();
        $this->assertNull($setting->webhook_url);
    }

    #[Test]
    public function delete_webhook_fails_without_bot_token(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/telegram-bot-settings/delete-webhook')
            ->assertRedirect('/dashboard/telegram-bot-settings')
            ->assertSessionHas('error', 'Bot token is not configured.');
    }

    #[Test]
    public function delete_webhook_handles_api_error(): void
    {
        Http::fake([
            'api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => false,
                'description' => 'bot not found',
            ], 400),
        ]);

        TelegramBotSetting::create([
            'tenant_id' => $this->tenant->id,
            'bot_token' => 'invalid-token',
            'webhook_url' => 'https://example.com/webhook/telegram',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/telegram-bot-settings/delete-webhook')
            ->assertRedirect('/dashboard/telegram-bot-settings')
            ->assertSessionHas('warning', 'Webhook URL cleared from settings, but failed to delete from Telegram API.');

        $setting = TelegramBotSetting::where('tenant_id', $this->tenant->id)->first();
        $this->assertNull($setting->webhook_url);
    }

    #[Test]
    public function telegram_settings_are_tenant_scoped(): void
    {
        // Create another tenant with settings
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'is_active' => true,
        ]);

        TelegramBotSetting::create([
            'tenant_id' => $otherTenant->id,
            'bot_token' => 'OTHER-TOKEN-123',
            'chat_id' => '-999999999',
        ]);

        // Current user should not see other tenant's settings
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/telegram-bot-settings')
            ->assertOk();

        $this->assertDatabaseMissing('telegram_bot_settings', [
            'chat_id' => '-999999999',
        ]);
    }
}
