<?php

namespace App\Services;

use App\Models\Tenant;
use Closure;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use LogicException;

/**
 * Service for tenant-namespaced cache operations.
 *
 * Prevents cross-tenant cache pollution by automatically
 * prefixing all cache keys with the current tenant ID.
 *
 * For non-tag-aware cache drivers, tracks all keys written
 * so that flush() can clean up only tenant-specific keys
 * without performing a full cache flush.
 */
class TenantCacheService
{
    /**
     * In-memory registry of keys written by this service instance.
     *
     * @var array<string, array<int, string>>
     */
    protected array $trackedKeys = [];

    /**
     * Generate a tenant-prefixed cache key without requiring an instance.
     *
     * @throws LogicException if no tenant context is set
     */
    public static function tenantKey(string $baseKey): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot generate tenant cache key: no tenant context is set.');
        }

        return "tenant:{$tenant->id}:{$baseKey}";
    }

    /**
     * Remember a value in the cache with tenant prefix (static convenience method).
     *
     * @param  \DateTimeInterface|\DateInterval|int|Closure  $ttl
     */
    public static function rememberByKey(string $baseKey, $ttl, Closure $callback): mixed
    {
        $key = self::tenantKey($baseKey);

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Remove an item from the cache with tenant prefix (static convenience method).
     */
    public static function forgetByKey(string $baseKey): bool
    {
        return Cache::forget(self::tenantKey($baseKey));
    }

    /**
     * Get a tenant-prefixed cache key.
     *
     * @throws LogicException if no tenant context is set
     */
    public function key(string $baseKey): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot generate tenant cache key: no tenant context is set.');
        }

        return "tenant:{$tenant->id}:{$baseKey}";
    }

    /**
     * Store an item in the cache and register it in Redis SET for cleanup.
     *
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     */
    public function put(string $baseKey, $value, $ttl = null): bool
    {
        $key = $this->key($baseKey);
        $this->trackKey($key);

        // Also register in Redis SET for cross-instance cleanup
        $tenant = Tenant::current();
        if ($tenant !== null) {
            self::registerKeyInRedisSet($key, $tenant->id);
        }

        return Cache::put($key, $value, $ttl);
    }

    /**
     * Retrieve an item from the cache with tenant prefix.
     */
    public function get(string $baseKey, mixed $default = null): mixed
    {
        return Cache::get($this->key($baseKey), $default);
    }

    /**
     * Get an item from the cache or store it with tenant prefix.
     *
     * @param  \DateTimeInterface|\DateInterval|int|Closure  $ttl
     */
    public function remember(string $baseKey, $ttl, Closure $callback): mixed
    {
        $key = $this->key($baseKey);
        $this->trackKey($key);

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Get an item from the cache or store it with tenant prefix (forever).
     */
    public function rememberForever(string $baseKey, Closure $callback): mixed
    {
        $key = $this->key($baseKey);
        $this->trackKey($key);

        return Cache::rememberForever($key, $callback);
    }

    /**
     * Remove an item from the cache with tenant prefix.
     */
    public function forget(string $baseKey): bool
    {
        return Cache::forget($this->key($baseKey));
    }

    /**
     * Clear all cache items for the current tenant.
     *
     * For tag-aware drivers, uses Cache::tags()->flush().
     * For non-tag-aware drivers, deletes all tracked keys individually.
     * If no keys were tracked, logs a warning instead of performing a full flush.
     */
    public function flush(): bool
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot flush tenant cache: no tenant context is set.');
        }

        if (Cache::supportsTags()) {
            return Cache::tags("tenant:{$tenant->id}")->flush();
        }

        // For non-tag-aware drivers, delete tracked keys individually.
        $tenantPrefix = "tenant:{$tenant->id}:";
        $keysToDelete = $this->trackedKeys[$tenant->id] ?? [];

        if (empty($keysToDelete)) {
            Log::warning(
                'TenantCacheService::flush() called on non-tag-aware cache driver with no tracked keys. Tenant keys were not cleaned up.',
                ['tenant_id' => $tenant->id]
            );

            return false;
        }

        $deletedCount = 0;

        foreach ($keysToDelete as $key) {
            if (str_starts_with($key, $tenantPrefix)) {
                Cache::forget($key);
                $deletedCount++;
            }
        }

        // Clear tracked keys for this tenant
        unset($this->trackedKeys[$tenant->id]);

        Log::info('Tenant cache flushed for non-tag-aware driver.', [
            'tenant_id' => $tenant->id,
            'keys_deleted' => $deletedCount,
        ]);

        return true;
    }

    /**
     * Get cache tags for the current tenant.
     *
     * @param  array<string>  $tags
     *
     * @throws LogicException if cache driver doesn't support tags
     */
    public function tags(array $tags): TaggedCache
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot get tenant cache tags: no tenant context is set.');
        }

        $allTags = array_merge(["tenant:{$tenant->id}"], $tags);

        return Cache::tags($allTags);
    }

    /**
     * Track a cache key for later cleanup.
     */
    protected function trackKey(string $key): void
    {
        $tenant = Tenant::current();

        if ($tenant !== null) {
            if (! isset($this->trackedKeys[$tenant->id])) {
                $this->trackedKeys[$tenant->id] = [];
            }

            $this->trackedKeys[$tenant->id][] = $key;
        }
    }

    /**
     * Register a cache key in a Redis SET for batch cleanup.
     *
     * Uses Redis SADD to add the key to a tenant-specific SET,
     * enabling efficient SCAN-free cleanup via SMEMBERS + UNLINK.
     *
     * Stores the FULL cache key (including Laravel's cache prefix) so that
     * clearTenantCache() can delete the correct Redis keys.
     */
    protected static function registerKeyInRedisSet(string $key, int $tenantId): void
    {
        try {
            $redis = Redis::connection();

            // Prefix the key with Laravel's cache prefix so UNLINK targets the correct Redis key
            $cachePrefix = (string) config('cache.prefix', 'laravel-cache-');
            $fullKey = $cachePrefix . $key;

            $redis->sadd("tenant:{$tenantId}:cache_keys", $fullKey);
        } catch (\Throwable $e) {
            Log::debug('Failed to register cache key in Redis SET.', [
                'tenant_id' => $tenantId,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all cache items for a specific tenant by ID.
     *
     * Uses Redis SSCAN to iteratively read keys from the tenant SET,
     * then batches UNLINK for async non-blocking deletion.
     * Avoids loading all keys into memory at once (SMEMBERS issue).
     */
    public static function clearTenantCache(int $tenantId): void
    {
        try {
            $redis = Redis::connection();
            $keySet = "tenant:{$tenantId}:cache_keys";
            $batchSize = 100;
            $totalDeleted = 0;

            // Use SSCAN to iterate over the SET in batches
            // The correct callback signature is: function (&$iterator, $keys)
            $iterator = 0;
            do {
                $keys = [];
                $result = $redis->sscan($keySet, $iterator, null, $batchSize);

                // sscan returns an array: [iterator_cursor, keys_array]
                if (is_array($result) && count($result) === 2) {
                    $iterator = (int) $result[0];
                    $keys = $result[1] ?? [];
                }

                if (empty($keys)) {
                    continue;
                }

                // Delete in batches using UNLINK (async, non-blocking)
                $chunks = array_chunk($keys, $batchSize);
                foreach ($chunks as $chunk) {
                    $redis->unlink(...$chunk);
                    $totalDeleted += count($chunk);
                }
            } while ($iterator > 0);

            // Remove the SET itself
            $redis->del($keySet);

            Log::info('Tenant cache cleared via Redis SSCAN + batch UNLINK.', [
                'tenant_id' => $tenantId,
                'keys_deleted' => $totalDeleted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to clear tenant cache via Redis SSCAN.', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            // Fallback: try SCAN-based cleanup
            try {
                $redis = Redis::connection();
                self::scanAndDeleteByPrefix($redis, "tenant:{$tenantId}:");
            } catch (\Throwable $fallbackError) {
                Log::error('Tenant cache cleanup fallback (SCAN) also failed.', [
                    'tenant_id' => $tenantId,
                    'error' => $fallbackError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Scan and delete keys matching a prefix (fallback method).
     *
     * Uses SCAN iterator to avoid blocking Redis on large keyspaces.
     */
    protected static function scanAndDeleteByPrefix($redis, string $prefix): void
    {
        $deletedCount = 0;

        $redis->scan(0, function ($keys) use (&$deletedCount, $redis): void {
            if (! empty($keys)) {
                $redis->unlink(...$keys);
                $deletedCount += count($keys);
            }
        }, $prefix, 100);

        Log::info('Tenant cache cleared via SCAN fallback.', [
            'prefix' => $prefix,
            'keys_deleted' => $deletedCount,
        ]);
    }
}
