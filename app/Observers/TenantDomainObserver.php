<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantResolver;

class TenantDomainObserver
{
    /**
     * Handle the TenantDomain "created" event.
     */
    public function created(TenantDomain $tenantDomain): void
    {
        $this->invalidateCache($tenantDomain);
    }

    /**
     * Handle the TenantDomain "updated" event.
     */
    public function updated(TenantDomain $tenantDomain): void
    {
        $this->invalidateCache($tenantDomain);
    }

    /**
     * Handle the TenantDomain "deleted" event.
     */
    public function deleted(TenantDomain $tenantDomain): void
    {
        $this->invalidateCache($tenantDomain);
    }

    /**
     * Invalidate all related caches when a tenant domain changes.
     */
    protected function invalidateCache(TenantDomain $tenantDomain): void
    {
        // Clear Tenant model cache
        $tenant = Tenant::find($tenantDomain->tenant_id);
        if ($tenant !== null) {
            $tenant->clearDomainCache();
        }

        // Clear TenantResolver cache
        $resolver = app(TenantResolver::class);
        $resolver->clearDomainCache($tenantDomain->domain);
    }
}
