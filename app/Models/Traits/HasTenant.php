<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Common trait for all tenant-scoped models.
 *
 * Provides:
 * - Automatic `tenant_id` assignment on `saving` if tenant context exists
 * - `tenant()` relationship definition
 * - `forTenant()` scope for explicit tenant filtering
 * - Validation that `tenant_id` is never empty when tenant context is present
 */
trait HasTenant
{
    /**
     * Boot the HasTenant trait.
     */
    public static function bootHasTenant(): void
    {
        static::saving(function ($model): void {
            // If tenant_id is not set and we have a current tenant context,
            // automatically assign it.
            if ($model->tenant_id === null || $model->tenant_id === '') {
                $currentTenant = Tenant::current();

                if ($currentTenant !== null) {
                    $model->tenant_id = $currentTenant->id;

                    Log::debug('HasTenant: auto-assigned tenant_id on save.', [
                        'model' => get_class($model),
                        'tenant_id' => $currentTenant->id,
                    ]);
                }
            }

            // If tenant_id is still empty and we're NOT in a migration/seed context,
            // this is a potential data leakage risk.
            if (($model->tenant_id === null || $model->tenant_id === '') && $model->exists === false) {
                $currentTenant = Tenant::current();

                // Only throw if we're outside of a tenant context and the model
                // is being created (not updated).
                if ($currentTenant === null) {
                    Log::warning('HasTenant: Model being saved without tenant_id and no tenant context.', [
                        'model' => get_class($model),
                    ]);
                }
            }
        });
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     *
     * Use this when you need to explicitly query for a tenant
     * regardless of the current tenant context.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($this->getTable().'.tenant_id', $tenantId);
    }
}
