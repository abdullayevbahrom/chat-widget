<?php

namespace App\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that automatically scopes queries to the current tenant.
 *
 * When a tenant context is set via Tenant::setCurrent(), this scope
 * ensures that queries on tenant-scoped models only return data
 * belonging to that tenant. Super-admin users bypass this scope.
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if no tenant context is set
        $currentTenant = Tenant::current();

        if ($currentTenant === null) {
            // No tenant context — return empty results for tenant-scoped models
            // to prevent data leakage across tenants
            $builder->whereRaw('1 = 0');

            return;
        }

        // Super-admin users can see all data
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return;
        }

        // Scope to the current tenant
        $builder->where($model->getTable().'.tenant_id', $currentTenant->id);
    }
}
