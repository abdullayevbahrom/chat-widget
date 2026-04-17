<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'is_super_admin', 'email_verified_at', 'telegram_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function isTenantUser(): bool
    {
        return ! $this->isSuperAdmin() && $this->tenant !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_to');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    public function createSanctumToken(string $name, array $abilities = []): NewAccessToken
    {
        $defaultAbilities = $abilities !== [] ? $abilities : ['tenant:read', 'tenant:write'];

        return $this->createToken($name, $defaultAbilities);
    }
}
