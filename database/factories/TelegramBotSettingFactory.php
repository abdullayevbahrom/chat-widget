<?php

namespace Database\Factories;

use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<TelegramBotSetting>
 */
class TelegramBotSettingFactory extends Factory
{
    protected $model = TelegramBotSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'bot_username' => $this->faker->userName.'_bot',
            'bot_name' => $this->faker->name,
            'bot_token_encrypted' => Crypt::encryptString($this->faker->numerify('#########') . ':' . $this->faker->bothify('????????????????')),
            'chat_id' => '-100'.$this->faker->numerify('############'),
            'telegram_admin_ids' => [$this->faker->numberBetween(100000000, 999999999)],
            'webhook_url' => 'https://example.com/api/telegram/webhook/'.$this->faker->slug,
            'is_active' => true,
            'last_webhook_status' => 'success',
        ];
    }
}
