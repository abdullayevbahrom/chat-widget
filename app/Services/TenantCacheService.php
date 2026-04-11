<?php

namespace App\Services;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Service for tenant-namespaced cache operations.
 *
 * Prevents cross-tenant cache pollution by automatically
 * prefixing all cache keys with the current tenant ID.
 */
class TenantCacheService
{
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
     * Store an item in the cache with tenant prefix.
     *
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     */
    public function put(string $baseKey, $value, $ttl = null): bool
    {
        return Cache::put($this->key($baseKey), $value, $ttl);
    }

    /**
     * Retrieve an item from the cache with tenant prefix.
     *
     * @return mixed
     */
    public function get(string $baseKey, mixed $default = null): mixed
    {
        return Cache::get($this->key($baseKey), $default);
    }

    /**
     * Get an item from the cache or store it with tenant prefix.
     *
     * @param  \DateTimeInterface|\DateInterval|int|\Closure  $ttl
     * @return mixed
     */
    public function remember(string $baseKey, $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->key($baseKey), $ttl, $callback);
    }

    /**
     * Get an item from the cache or store it with tenant prefix (forever).
     *
     * @return mixed
     */
    public function rememberForever(string $baseKey, Closure $callback): mixed
    {
        return Cache::rememberForever($this->key($baseKey), $callback);
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
     * Note: This only works with cache drivers that support tags.
     * For other drivers, you need to track keys manually.
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

        // For non-tag-aware drivers, we can only flush the entire cache
        // or rely on TTL expiration. Log a warning.
        \Illuminate\Support\Facades\Log::warning(
            'TenantCacheService::flush() called on non-tag-aware cache driver. Full cache flush performed.',
            ['tenant_id' => $tenant->id]
        );

        return Cache::flush();
    }

    /**
     * Get cache tags for the current tenant.
     *
     * @param  array<string>  $tags
     * @return \Illuminate\Cache\TaggedCache
     *
     * @throws LogicException if cache driver doesn't support tags
     */
    public function tags(array $tags): \Illuminate\Cache\TaggedCache
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot get tenant cache tags: no tenant context is set.');
        }

        $allTags = array_merge(["tenant:{$tenant->id}"], $tags);

        return Cache::tags($allTags);
    }
}
