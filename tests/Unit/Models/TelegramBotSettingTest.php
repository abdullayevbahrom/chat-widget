<?php

namespace Tests\Unit\Models;

use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramBotSettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $setting->tenant);
        $this->assertEquals($tenant->id, $setting->tenant->id);
    }

    #[Test]
    public function it_encrypts_bot_token_on_set(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->bot_token = '123456789:ABCdef-GHIjkl_MNOpqr';

        $this->assertNull($setting->bot_token_encrypted); // not saved yet
        $setting->save();

        $this->assertNotNull($setting->bot_token_encrypted);
        $this->assertNotEquals('123456789:ABCdef-GHIjkl_MNOpqr', $setting->bot_token_encrypted);
    }

    #[Test]
    public function it_decrypts_bot_token_on_get(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->bot_token = '123456789:ABCdef-GHIjkl_MNOpqr';
        $setting->save();

        $this->assertEquals('123456789:ABCdef-GHIjkl_MNOpqr', $setting->bot_token);
    }

    #[Test]
    public function it_returns_null_for_empty_bot_token(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'bot_token_encrypted' => null,
        ]);

        $this->assertNull($setting->bot_token);
    }

    #[Test]
    public function it_encrypts_webhook_secret_on_set(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->webhook_secret = 'my-secret-token';
        $setting->save();

        $this->assertNotNull($setting->webhook_secret_encrypted);
        $this->assertNotEquals('my-secret-token', $setting->webhook_secret_encrypted);
    }

    #[Test]
    public function it_decrypts_webhook_secret_on_get(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->webhook_secret = 'my-secret-token';
        $setting->save();

        $this->assertEquals('my-secret-token', $setting->webhook_secret);
    }

    #[Test]
    public function it_handles_null_webhook_secret(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_secret_encrypted' => null,
        ]);

        $this->assertNull($setting->webhook_secret);
    }

    #[Test]
    public function it_clears_webhook_secret_on_null_set(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->webhook_secret = 'initial-secret';
        $setting->save();

        $setting->webhook_secret = null;
        $setting->save();

        $this->assertNull($setting->fresh()->webhook_secret_encrypted);
    }

    #[Test]
    public function it_casts_telegram_admin_ids_to_array(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'telegram_admin_ids' => [123, 456, 789],
        ]);

        $this->assertIsArray($setting->telegram_admin_ids);
        $this->assertEquals(3, count($setting->telegram_admin_ids));
        $this->assertContains(123, $setting->telegram_admin_ids);
    }

    #[Test]
    public function it_stores_chat_id(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'chat_id' => '-1001234567890',
        ]);

        $this->assertEquals('-1001234567890', $setting->chat_id);
    }

    #[Test]
    public function it_casts_is_active_to_boolean(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => 1,
        ]);

        $this->assertIsBool($setting->is_active);
    }

    #[Test]
    public function encrypted_fields_are_hidden(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = new TelegramBotSetting();
        $setting->tenant_id = $tenant->id;
        $setting->bot_token = '123456:ABC-DEF';
        $setting->webhook_secret = 'secret123';
        $setting->save();

        $array = $setting->toArray();

        $this->assertArrayNotHasKey('bot_token_encrypted', $array);
        $this->assertArrayNotHasKey('webhook_secret_encrypted', $array);
    }

    #[Test]
    public function it_has_webhook_error_tracking_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $setting = TelegramBotSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'last_webhook_status' => 'failed',
        ]);

        $this->assertEquals('failed', $setting->last_webhook_status);
    }
}
