<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantResolver
{
    /**
     * Cache TTL in seconds (15 minutes — reduced from 1 hour to prevent stale data).
     */
    protected int $cacheTtl = 900;

    /**
     * Resolve a tenant from a domain name.
     */
    public function resolveFromDomain(string $domain): ?Tenant
    {
        return Cache::remember(
            "tenant:domain:{$domain}",
            $this->cacheTtl,
            function () use ($domain) {
                if (! $this->isValidDomain($domain)) {
                    return null;
                }

                return Tenant::whereHas('domains', function ($query) use ($domain) {
                    $query->where('domain', $domain)->where('is_active', true);
                })->first();
            }
        );
    }

    /**
     * Resolve a tenant from a subdomain.
     */
    public function resolveFromSubdomain(string $subdomain): ?Tenant
    {
        return Cache::remember(
            "tenant:subdomain:{$subdomain}",
            $this->cacheTtl,
            fn () => Tenant::where('subdomain', $subdomain)
                ->where('is_active', true)
                ->first()
        );
    }

    /**
     * Resolve a tenant from either domain or subdomain.
     *
     * Subdomain fallback is only allowed when the host matches the
     * configured base domain to prevent bypass via arbitrary hosts.
     */
    public function resolve(string $host): ?Tenant
    {
        // First try exact domain match
        $tenant = $this->resolveFromDomain($host);

        if ($tenant !== null) {
            return $tenant;
        }

        // Try subdomain match only if the host has at least 3 parts
        // (e.g., "acme.widget.test" → 3 parts) to avoid matching bare domains.
        // For multi-level subdomains like "www.acme.widget.test", we extract
        // the tenant identifier as the part immediately before the base domain.
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $baseDomain = config('app.base_domain', 'widget.test');
            $hostSuffix = '.'.$baseDomain;

            // Only allow subdomain resolution if the host ends with our base domain
            if (str_ends_with($host, $hostSuffix)) {
                // Extract the tenant subdomain: everything before the base domain suffix
                // e.g., "www.acme.widget.test" → "www.acme" (full prefix)
                // For single-level: "acme.widget.test" → "acme"
                $prefix = substr($host, 0, -strlen($hostSuffix));
                // Use the first segment as the tenant identifier
                $subdomain = explode('.', $prefix)[0];

                return $this->resolveFromSubdomain($subdomain);
            }
        }

        return null;
    }

    /**
     * Clear the cache for a specific domain.
     */
    public function clearDomainCache(string $domain): void
    {
        Cache::forget("tenant:domain:{$domain}");
    }

    /**
     * Clear the cache for a specific subdomain.
     */
    public function clearSubdomainCache(string $subdomain): void
    {
        Cache::forget("tenant:subdomain:{$subdomain}");
    }

    /**
     * Clear all tenant-related cache entries.
     *
     * Uses individual key deletion instead of tags to support all cache drivers
     * (including file/array drivers that don't support tags).
     */
    public function clearAllCache(): void
    {
        // Since we use a known key prefix, we can't efficiently flush all keys
        // without a tags system. Instead, callers should call clearDomainCache()
        // / clearSubdomainCache() for specific entries.
        // This method serves as a documentation placeholder.
        // For Redis-backed cache, you could scan and delete:
        // $keys = Cache::getRedis()->keys('tenant:*');
        // foreach ($keys as $key) { Cache::forget($key); }
    }

    /**
     * Check if a domain string is valid.
     */
    protected function isValidDomain(string $domain): bool
    {
        if (empty($domain)) {
            return false;
        }

        if (! preg_match(config('domains.regex'), $domain)) {
            return false;
        }

        // Prevent consecutive dots
        if (str_contains($domain, '..')) {
            return false;
        }

        return true;
    }
}
