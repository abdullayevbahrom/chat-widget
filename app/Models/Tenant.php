<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Cache\RedisStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The current tenant instance.
     */
    protected static ?Tenant $currentTenant = null;

    /**
     * Set the current tenant.
     */
    public static function setCurrent(Tenant $tenant): void
    {
        static::$currentTenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public static function current(): ?Tenant
    {
        return static::$currentTenant;
    }

    /**
     * Clear the current tenant (useful for middleware reset in long-running processes).
     */
    public static function clearCurrent(): void
    {
        static::$currentTenant = null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'is_active',
        'plan',
        'subscription_expires_at',
        'settings',
        // Profile fields
        'company_name',
        'company_registration_number',
        'tax_id',
        'company_address',
        'company_city',
        'company_country',
        'contact_phone',
        'contact_email',
        'website',
        'logo_path',
        'primary_contact_name',
        'primary_contact_title',
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
            'subscription_expires_at' => 'datetime',
            'settings' => 'json',
        ];
    }

    /**
     * Check if the tenant is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if the tenant has an active subscription.
     */
    public function isSubscribed(): bool
    {
        if ($this->plan === 'free') {
            return false;
        }

        if ($this->subscription_expires_at === null) {
            return true;
        }

        return $this->subscription_expires_at->isFuture();
    }

    /**
     * Check if the tenant has a custom domain.
     */
    public function hasCustomDomain(): bool
    {
        return filled($this->domain);
    }

    /**
     * Get the users that belong to this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the conversations for this tenant.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the domains associated with this tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Get only the active domains for this tenant.
     */
    public function activeDomains(): HasMany
    {
        return $this->hasMany(TenantDomain::class)->where('is_active', true);
    }

    /**
     * Cache prefix for domain-related operations.
     */
    protected const DOMAIN_CACHE_PREFIX = 'tenant_domain:';

    /**
     * Check if the tenant has a specific domain in its whitelist.
     * Results are cached to avoid repeated database queries.
     */
    public function hasDomain(string $domain): bool
    {
        $cacheKey = self::DOMAIN_CACHE_PREFIX."{$this->id}:has:{$domain}";
        $cacheTtl = now()->addMinutes(15);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($domain) {
            return $this->domains()->where('domain', $domain)->where('is_active', true)->exists();
        });
    }

    /**
     * Clear the domain cache when domains are modified.
     */
    public function clearDomainCache(): void
    {
        // Clear the domains list cache
        Cache::forget(self::DOMAIN_CACHE_PREFIX."{$this->id}:domains");

        // Clear all individual hasDomain caches
        // We use a wildcard pattern via the cache driver's native capabilities
        $this->clearDomainCacheByPattern(self::DOMAIN_CACHE_PREFIX."{$this->id}:");
    }

    /**
     * Clear cache entries matching a prefix pattern.
     * Works with Redis, file, and array cache drivers.
     */
    protected function clearDomainCacheByPattern(string $prefix): void
    {
        $driver = Cache::driver();

        // For Redis, scan and delete keys matching the pattern
        if ($driver instanceof RedisStore) {
            $redis = $driver->connection();
            $cursor = null;
            $pattern = $prefix.'*';
            do {
                $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                if (! empty($keys)) {
                    $driver->delete($keys);
                }
            } while ($cursor !== '0' && $cursor !== 0);

            return;
        }

        // For drivers that support tags, flush the tenant's domain tag
        if (Cache::supportsTags()) {
            Cache::tags("tenant:{$this->id}:domains")->flush();
        }

        // For file/array drivers, we rely on TTL expiration.
        // The prefix-based forget calls in observers handle explicit clears.
    }
}
