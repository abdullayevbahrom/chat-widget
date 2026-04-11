<?php

namespace App\Models;

use App\Observers\TenantDomainObserver;
use App\Scopes\TenantScope;
use Database\Factories\TenantDomainFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([TenantDomainObserver::class])]
class TenantDomain extends Model
{
    /** @use HasFactory<TenantDomainFactory> */
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
        'domain',
        'is_active',
        'is_verified',
        'verification_token',
        'verified_at',
        'notes',
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
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns this domain.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if this domain is valid (active).
     */
    public function isValid(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Generate a new verification token.
     */
    public function generateVerificationToken(): string
    {
        $this->verification_token = bin2hex(random_bytes(32));
        $this->is_verified = false;
        $this->verified_at = null;
        $this->save();

        return $this->verification_token;
    }

    /**
     * Mark the domain as verified.
     */
    public function markAsVerified(): void
    {
        $this->is_verified = true;
        $this->verified_at = now();
        $this->verification_token = null;
        $this->save();
    }
}
