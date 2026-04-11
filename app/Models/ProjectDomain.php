<?php

namespace App\Models;

use App\Observers\ProjectDomainObserver;
use App\Scopes\TenantScope;
use Database\Factories\ProjectDomainFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[ObservedBy([ProjectDomainObserver::class])]
class ProjectDomain extends Model
{
    /** @use HasFactory<ProjectDomainFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope('project'));
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

    protected function domain(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeDomainInput($value),
        );
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
        return $this->verification_status === self::STATUS_VERIFIED && $this->is_active;
    }

    /**
     * Mark this domain as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verification_status' => self::STATUS_VERIFIED,
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
            'verification_status' => self::STATUS_FAILED,
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
            'verification_status' => self::STATUS_PENDING,
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

    public static function normalizeDomainInput(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $candidate = trim($value);
        $parts = parse_url($candidate);

        if (($parts === false || ! isset($parts['scheme'], $parts['host'])) && ! str_contains($candidate, '://')) {
            $parts = parse_url('https://'.$candidate);
        }

        if (
            $parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! is_string($parts['scheme'])
            || ! is_string($parts['host'])
        ) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && is_int($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf('%s://%s%s', $scheme, $host, $port);
    }

    public function getHostForVerification(): ?string
    {
        $parts = parse_url($this->domain);

        return $parts !== false && isset($parts['host']) && is_string($parts['host'])
            ? strtolower($parts['host'])
            : null;
    }

    public static function existsForProject(int $projectId, string $domain, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('project_id', $projectId)
            ->where('domain', $domain)
            ->when(
                $ignoreId !== null,
                fn ($query) => $query->whereKeyNot($ignoreId),
            )
            ->exists();
    }
}
