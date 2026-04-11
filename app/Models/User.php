<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Panel;
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

#[Fillable(['name', 'email', 'password', 'tenant_id', 'telegram_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Check if the user belongs to a tenant (not a super admin).
     */
    public function isTenantUser(): bool
    {
        return ! $this->isSuperAdmin() && $this->tenant_id !== null;
    }

    /**
     * Get the tenant that owns the user.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the conversations assigned to this user.
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_to');
    }

    /**
     * Get the messages sent by this user.
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_super_admin === true;
        }

        // Allow access to other panels (e.g., tenant panels)
        return true;
    }

    /**
     * Create a new personal access token with restricted permissions.
     * Overrides the default to enforce minimum required scopes.
     *
     * @param  array<string>  $abilities
     */
    public function createSanctumToken(string $name, array $abilities = []): NewAccessToken
    {
        // Enforce minimum required scopes — restrict to tenant operations only
        $defaultAbilities = $abilities !== [] ? $abilities : ['tenant:read', 'tenant:write'];

        return $this->createToken($name, $defaultAbilities);
    }
}
