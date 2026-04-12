<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantResolver
{
    /**
     * Host-based tenant resolution has been removed.
     * Tenant context is now established from the authenticated user only.
     */
    public function resolve(string $host): ?Tenant
    {
        return null;
    }

    public function clearDomainCache(string $domain): void
    {
        Cache::forget("tenant:domain:{$domain}");
    }

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
        // No-op: host-based tenant resolution cache is deprecated.
    }
}
