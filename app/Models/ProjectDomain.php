<?php

namespace App\Models;

use App\Observers\ProjectDomainObserver;
use App\Scopes\TenantScope;
use Database\Factories\ProjectDomainFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[ObservedBy([ProjectDomainObserver::class])]
class ProjectDomain extends Model
{
    /** @use HasFactory<ProjectDomainFactory> */
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
        'project_id',
        'domain',
        'verification_status',
        'verification_token',
        'verified_at',
        'verification_error',
        'is_active',
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
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns this domain.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Check if this domain is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified' && $this->is_active;
    }

    /**
     * Mark this domain as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verification_error' => null,
        ]);
    }

    /**
     * Mark this domain as failed verification.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'verification_status' => 'failed',
            'verification_error' => $error,
        ]);
    }

    /**
     * Generate a new verification token for this domain.
     */
    public function generateVerificationToken(): string
    {
        $token = Str::random(32);

        $this->update([
            'verification_token' => $token,
            'verification_status' => 'pending',
            'verified_at' => null,
            'verification_error' => null,
        ]);

        return $token;
    }

    /**
     * Check if the verification token is still valid (not expired).
     * Token TTL: 24 hours.
     */
    public function isVerificationTokenValid(): bool
    {
        if (blank($this->verification_token)) {
            return false;
        }

        return $this->updated_at->isAfter(now()->subHours(24));
    }
}
