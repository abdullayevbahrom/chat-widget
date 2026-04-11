<?php

namespace App\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                Log::debug('TenantScope bypassed for super admin.', [
                    'model' => get_class($model),
                    'user_id' => $user->id,
                ]);

                return;
            }
        }

        // Skip if no tenant context is set
        $currentTenant = Tenant::current();

        if ($currentTenant === null) {
            // No tenant context — return empty results for tenant-scoped models
            // to prevent data leakage across tenants.
            // Uses Tenant::withoutTenantContext() static property as an explicit
            // bypass signal for intentional context-free queries (e.g. widget key validation).
            if (Tenant::isBypassingContext()) {
                Log::debug('TenantScope skipped: tenant context bypass is active.', [
                    'model' => get_class($model),
                ]);

                return;
            }

            Log::debug('TenantScope applied: no tenant context, returning empty result.', [
                'model' => get_class($model),
            ]);

            $builder->whereRaw('1 = 0');

            return;
        }

        Log::debug('TenantScope applied.', [
            'model' => get_class($model),
            'tenant_id' => $currentTenant->id,
        ]);

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
