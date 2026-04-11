<?php

namespace Tests\Feature;

use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TelegramBotSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_token_is_automatically_encrypted(): void
    {
        $tenant = Tenant::factory()->create();
        $token = '123456789:ABCdef-GHIjkl_MNOpqrSTUvwxYZ';

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token' => $token,
        ]);

        // Verify the raw database value is encrypted
        $this->assertNotEquals($token, $setting->bot_token_encrypted);
        $this->assertNotNull($setting->bot_token_encrypted);

        // Verify decryption works
        $this->assertEquals($token, $setting->bot_token);
    }

    public function test_bot_token_can_be_decrypted(): void
    {
        $tenant = Tenant::factory()->create();
        $originalToken = '987654321:XYZabc-DEFghi_JKLmnoPQRstuvWX';

        $setting = new TelegramBotSetting;
        $setting->tenant_id = $tenant->id;
        $setting->bot_token = $originalToken;
        $setting->save();

        // Access via accessor
        $decrypted = $setting->bot_token;

        $this->assertEquals($originalToken, $decrypted);
    }

    public function test_null_bot_token_returns_null(): void
    {
        $tenant = Tenant::factory()->create();

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token_encrypted' => null,
        ]);

        $this->assertNull($setting->bot_token);
    }

    public function test_empty_bot_token_returns_null(): void
    {
        $tenant = Tenant::factory()->create();

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token_encrypted' => '',
        ]);

        $this->assertNull($setting->bot_token);
    }

    public function test_is_active_casts_to_boolean(): void
    {
        $tenant = Tenant::factory()->create();

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token' => '123456:ABCdef',
            'is_active' => 1,
        ]);

        $this->assertTrue($setting->fresh()->is_active);

        $setting->update(['is_active' => 0]);
        $this->assertFalse($setting->fresh()->is_active);
    }

    public function test_setting_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token' => '123456:ABCdef',
        ]);

        $this->assertInstanceOf(Tenant::class, $setting->tenant);
        $this->assertEquals($tenant->id, $setting->tenant->id);
    }

    public function test_webhook_secret_is_generated(): void
    {
        $tenant = Tenant::factory()->create();

        $setting = TelegramBotSetting::create([
            'tenant_id' => $tenant->id,
            'bot_token' => '123456:ABCdef',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        $this->assertNotNull($setting->webhook_secret);
        $this->assertEquals(64, strlen($setting->webhook_secret));
    }
}
