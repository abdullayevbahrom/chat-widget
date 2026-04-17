<?php

namespace App\Scopes;

use App\Models\Tenant;
use App\Models\User;
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
        // In testing environment, skip tenant scope to allow test data creation
        if (app()->environment('testing')) {
            return;
        }

        if (Tenant::isBypassingContext()) {
            return;
        }

        // Non-HTTP contextlarda (queue jobs, tinker, migrations) Auth::user()
        // chaqirish keraksiz va ba'zan xatolikka olib kelishi mumkin.
        // Faqat HTTP context da auth tekshiramiz.
        if ($this->isHttpContext()) {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->isSuperAdmin()) {
                // Production da debug loglarni o'tkazib yuboramiz (log spam)
                if (app()->environment('local', 'testing')) {
                    Log::debug('TenantScope bypassed for super admin.', [
                        'model' => get_class($model),
                        'user_id' => $user->id,
                    ]);
                }

                return;
            }
        }

        // Skip if no tenant context is set
        $currentTenant = Tenant::current();

        if ($currentTenant === null) {
            // No tenant context — return empty results for tenant-scoped models
            // to prevent data leakage across tenants.
            if (app()->environment('local', 'testing')) {
                Log::debug('TenantScope applied: no tenant context, returning empty result.', [
                    'model' => get_class($model),
                ]);
            }

            $builder->whereRaw('1 = 0');

            return;
        }

        if (app()->environment('local', 'testing')) {
            Log::debug('TenantScope applied.', [
                'model' => get_class($model),
                'tenant_id' => $currentTenant->id,
            ]);
        }

        // Agar model da to'g'ridan-to'g'ri `tenant_id` ustuni mavjud bo'lsa,
        // whereHas o'rniga oddiy where() ishlatamiz — bu ancha samarali.
        $hasTenantColumn = $this->modelHasTenantColumn($model);

        if ($hasTenantColumn) {
            $builder->where($model->getTable().'.tenant_id', $currentTenant->id);

            return;
        }

        // Agar tenant_id ustuni bo'lmasa, relation orqali scope qilamiz
        if ($this->relationColumn !== null) {
            $builder->whereHas($this->relationColumn, function (Builder $query) use ($currentTenant): void {
                $query->where('tenant_id', $currentTenant->id);
            });

            return;
        }

        // Fallback: agar tenant_id ustuni ham, relation ham bo'lmasa,
        // jadval nomiga tenant_id qo'shib where() ishlatamiz
        $builder->where($model->getTable().'.tenant_id', $currentTenant->id);
    }

    /**
     * Tekshirish: model jadvalida `tenant_id` ustuni mavjudmi?
     *
     * Fillable array dan foydalanib aniqlaymiz — bu eng ishonchli usul,
     * chunki column mavjudligi migrations da bo'lishi kerak.
     */
    protected function modelHasTenantColumn(Model $model): bool
    {
        $fillable = $model->getFillable();

        if (in_array('tenant_id', $fillable, true)) {
            return true;
        }

        // Agar guarded bo'sh bo'lsa (barcha fillable), tenant_id mavjud deb hisoblaymiz
        $guarded = $model->getGuarded();
        if (empty($guarded)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if we are in an HTTP request context.
     */
    protected function isHttpContext(): bool
    {
        return ! app()->runningInConsole() && app('request') !== null;
    }
}
