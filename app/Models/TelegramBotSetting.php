<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TelegramBotSetting extends Model
{
    use HasFactory;

    protected $table = 'telegram_bot_settings';

    protected $fillable = [
        'tenant_id',
        'bot_token_encrypted',
        'bot_username',
        'bot_name',
        'chat_id',
        'webhook_secret',
        'is_active',
        'allowed_admin_ids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_admin_ids' => 'json',
    ];

    /**
     * Get the decrypted bot token.
     */
    public function getBotTokenAttribute(): ?string
    {
        if (blank($this->bot_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->bot_token_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted bot token.
     */
    public function setBotTokenAttribute(?string $token): void
    {
        if (blank($token)) {
            $this->bot_token_encrypted = null;
            return;
        }

        $this->bot_token_encrypted = Crypt::encryptString($token);
    }

    /**
     * Check if an admin ID is allowed.
     */
    public function isAdminAllowed(int $adminId): bool
    {
        $allowedIds = $this->allowed_admin_ids ?? [];

        if (empty($allowedIds)) {
            return true; // No restrictions
        }

        return in_array($adminId, $allowedIds, true);
    }

    /**
     * Belongs to tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
