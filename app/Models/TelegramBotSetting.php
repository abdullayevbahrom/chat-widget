<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TelegramBotSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'bot_token_encrypted',
        'bot_username',
        'bot_name',
        'chat_id',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'last_webhook_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns this setting.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the decrypted bot token.
     *
     * @return string|null
     */
    public function getBotTokenAttribute(): ?string
    {
        if ($this->bot_token_encrypted === null || $this->bot_token_encrypted === '') {
            return null;
        }

        return Crypt::decryptString($this->bot_token_encrypted);
    }

    /**
     * Set the bot token (automatically encrypts before storage).
     */
    public function setBotTokenAttribute(string $value): void
    {
        $this->bot_token_encrypted = Crypt::encryptString($value);
    }
}
