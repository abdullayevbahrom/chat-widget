<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Cache\RedisStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The current tenant instance.
     */
    protected static ?Tenant $currentTenant = null;

    /**
     * Flag to temporarily bypass tenant scope enforcement.
     *
     * When true, TenantScope will not apply the `whereRaw('1 = 0')` fallback
     * even when no tenant context is set. Use sparingly for intentional
     * context-free queries (e.g. widget key validation).
     *
     * Uses Laravel's Context facade for request-scoped storage, which
     * automatically resets between HTTP requests and queue jobs.
     */
    protected static bool $bypassTenantContext = false;

    /**
     * Context key for request-scoped bypass flag.
     */
    protected const BYPASS_CONTEXT_KEY = 'tenant.bypass_scope';

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
     * Temporarily bypass tenant context enforcement within a callback.
     *
     * This is the preferred way to execute code without tenant scope,
     * as it automatically restores the previous state when the callback
     * completes (even if an exception is thrown).
     *
     * Uses Laravel's Context facade for request-scoped storage that
     * automatically resets between requests and queue jobs.
     *
     * Example:
     *   $project = Tenant::withoutTenantContext(fn () => Project::find(1));
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutTenantContext(callable $callback): mixed
    {
        $previousBypass = static::isBypassingContext();
        static::setBypass(true);

        try {
            return $callback();
        } finally {
            static::setBypass($previousBypass);
        }
    }

    /**
     * Check if tenant context bypass is currently active.
     *
     * Checks request-scoped Context first (for automatic cleanup in
     * long-running processes), then falls back to static property.
     */
    public static function isBypassingContext(): bool
    {
        // Check Context first — this is request-scoped and auto-resets
        $contextValue = Context::get(static::BYPASS_CONTEXT_KEY, null);

        if ($contextValue !== null) {
            return (bool) $contextValue;
        }

        // Fallback to static property for backward compatibility
        return static::$bypassTenantContext;
    }

    /**
     * Set the bypass flag in Context (request-scoped) with static fallback.
     */
    public static function setBypass(bool $value): void
    {
        Context::add(static::BYPASS_CONTEXT_KEY, $value);
        static::$bypassTenantContext = $value;
    }

    /**
     * Re-enable tenant context enforcement after a bypass.
     *
     * Note: Prefer using withoutTenantContext(callback) instead,
     * which automatically restores the bypass state.
     */
    public static function enableTenantContext(): void
    {
        static::setBypass(false);
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
        'settings',
        // Plan & status
        'plan',
        'is_active',
        'subscription_expires_at',
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
     * The attributes that should be guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
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
     * Get the messages sent by this tenant.
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
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
     * Get the Telegram bot settings for this tenant.
     */
    public function telegramBotSetting(): HasOne
    {
        return $this->hasOne(TelegramBotSetting::class);
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

    /**
     * Invalidate all sessions for this tenant's users.
     *
     * Call this when a tenant is deactivated to force all users to log out.
     * Uses Redis SET indexing (laravel_session:user_ids:{userId}) to avoid
     * regex parsing on session data.
     */
    public function invalidateSessions(): void
    {
        // Get all users belonging to this tenant
        $userIds = $this->users()->pluck('id')->toArray();

        if (empty($userIds)) {
            return;
        }

        // For database session driver, delete sessions for these users
        $sessionTable = config('session.table', 'sessions');

        try {
            DB::table($sessionTable)
                ->whereIn('user_id', $userIds)
                ->delete();
        } catch (\Exception $e) {
            Log::warning(
                'Failed to invalidate tenant sessions (session table may not exist).',
                [
                    'tenant_id' => $this->id,
                    'error' => $e->getMessage(),
                ]
            );
        }

        // For Redis session driver, use SET-based index instead of regex parsing
        $cacheDriver = Cache::driver();

        if ($cacheDriver instanceof RedisStore) {
            $redis = Redis::connection();
            $deletedCount = 0;

            // Each session should be registered in a SET: laravel_session:tenant_users
            // containing session keys. We iterate through user-specific SETs.
            foreach ($userIds as $userId) {
                $sessionKeySet = "laravel_session:user_ids:{$userId}";
                $sessionKeys = $redis->smembers($sessionKeySet);

                if (! empty($sessionKeys)) {
                    $redis->unlink(...$sessionKeys);
                    $deletedCount += count($sessionKeys);
                    $redis->del($sessionKeySet);
                }
            }

            // Fallback: if SET-based indexing is not yet in use, scan by cookie prefix
            // and validate user_id by checking the SET without parsing session data
            if ($deletedCount === 0) {
                $cookiePrefix = config('session.cookie', 'laravel_session');
                $cursor = null;

                do {
                    $result = $redis->scan($cursor, ['match' => "{$cookiePrefix}:*", 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];

                    // Instead of regex parsing, check if session metadata contains user_id
                    // by reading the user_id index SET for each key
                    $keysToDelete = [];
                    foreach ($userIds as $userId) {
                        $userSessions = $redis->smembers("laravel_session:user_ids:{$userId}");
                        $keysToDelete = array_merge($keysToDelete, array_intersect($keys, $userSessions));
                    }

                    if (! empty($keysToDelete)) {
                        $redis->unlink(...$keysToDelete);
                        $deletedCount += count($keysToDelete);
                    }
                } while ($cursor !== '0' && $cursor !== 0);
            }

            Log::info('Tenant sessions invalidated via Redis SET.', [
                'tenant_id' => $this->id,
                'user_count' => count($userIds),
                'sessions_deleted' => $deletedCount,
            ]);
        }

        Log::info('Tenant sessions invalidated.', [
            'tenant_id' => $this->id,
            'user_count' => count($userIds),
        ]);
    }
}
