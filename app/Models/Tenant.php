<?php

namespace App\Models;

use Illuminate\Cache\RedisStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Tenant extends Model
{
    use HasFactory;

    protected static ?self $currentTenant = null;

    protected static bool $bypassContext = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'plan',
        'settings',
        'is_active',
        'subscription_expires_at',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * Set the current tenant context.
     */
    public static function setCurrent(?self $tenant = null): void
    {
        static::$currentTenant = $tenant;
    }

    /**
     * Get the current tenant context.
     */
    public static function current(): ?self
    {
        return static::$currentTenant;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clearCurrent(): void
    {
        static::$currentTenant = null;
    }

    public static function setBypass(bool $enabled): void
    {
        static::$bypassContext = $enabled;
    }

    public static function disableBypass(): void
    {
        static::$bypassContext = false;
    }

    public static function isBypassingContext(): bool
    {
        return static::$bypassContext;
    }

    /**
     * Run a callback without tenant context checks and then restore the prior state.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutTenantContext(callable $callback): mixed
    {
        $previousTenant = static::$currentTenant;
        $previousBypass = static::$bypassContext;

        static::$currentTenant = null;
        static::$bypassContext = true;

        try {
            return $callback();
        } finally {
            static::$currentTenant = $previousTenant;
            static::$bypassContext = $previousBypass;
        }
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'subscription_expires_at' => 'datetime',
            'settings' => 'json',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

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
