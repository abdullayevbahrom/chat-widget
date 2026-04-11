<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TelegramBotSetting extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'bot_token',
        'webhook_secret',
        'bot_username',
        'bot_name',
        'chat_id',
        'telegram_admin_ids',
        'webhook_url',
        'is_active',
        'last_webhook_status',
    ];

    /**
     * The attributes that should be hidden from arrays/JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'bot_token_encrypted',
        'webhook_secret_encrypted',
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
            'telegram_admin_ids' => 'array',
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

    /**
     * Get the decrypted webhook secret.
     */
    public function getWebhookSecretAttribute(): ?string
    {
        if ($this->webhook_secret_encrypted === null || $this->webhook_secret_encrypted === '') {
            return null;
        }

        return Crypt::decryptString($this->webhook_secret_encrypted);
    }

    /**
     * Set the webhook secret (automatically encrypts before storage).
     */
    public function setWebhookSecretAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->webhook_secret_encrypted = null;

            return;
        }

        $this->webhook_secret_encrypted = Crypt::encryptString($value);
    }
}
