<?php

namespace App\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that automatically scopes queries to the current tenant.
 *
 * When a tenant context is set via Tenant::setCurrent(), this scope
 * ensures that queries on tenant-scoped models only return data
 * belonging to that tenant. Super-admin users bypass this scope.
 *
 * If the model does not have a direct `tenant_id` column, you may set
 * `$relationColumn` to scope via a relationship using `whereHas`.
 */
class TenantScope implements Scope
{
    /**
     * Create a new TenantScope instance.
     *
     * @param  string|null  $relationColumn  Relationship name for whereHas fallback (e.g. 'conversation')
     */
    public function __construct(
        protected ?string $relationColumn = null,
    ) {}

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Non-HTTP contextlarda (queue jobs, tinker, migrations) auth mavjud emas
        // Auth::check() barcha guard'larni tekshiradi (web, sanctum token, etc.)
        // Auth::guard('web') faqat session-based auth bilan ishlaydi,
        // Sanctum API token orqali kirgan super adminlar bypass qila olmaydi.
        if (Auth::check()) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->isSuperAdmin()) {
                return;
            }
        }

        // Skip if no tenant context is set
        $currentTenant = Tenant::current();

        if ($currentTenant === null) {
            // No tenant context — return empty results for tenant-scoped models
            // to prevent data leakage across tenants.
            //
            // Why `whereRaw('1 = 0')` instead of throwing an exception?
            // - Some code paths intentionally query tenant-scoped models outside a
            //   tenant context (e.g. admin dashboards, cross-tenant reports).
            // - Throwing would break those flows; returning an empty result set
            //   is a safe default that prevents accidental data leakage while
            //   still allowing explicit bypass via withoutGlobalScopes().
            // - This expression is database-agnostic and produces zero rows
            //   regardless of the underlying table contents.
            $builder->whereRaw('1 = 0');

            return;
        }

        // If a relation column is specified, scope via the relationship
        if ($this->relationColumn !== null) {
            $builder->whereHas($this->relationColumn, function (Builder $query) use ($currentTenant): void {
                $query->where('tenant_id', $currentTenant->id);
            });

            return;
        }

        // Scope to the current tenant via direct column
        $builder->where($model->getTable().'.tenant_id', $currentTenant->id);
    }
}
