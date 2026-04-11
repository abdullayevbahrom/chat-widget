<?php

namespace App\Rules;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule that ensures a domain is unique within a specific tenant.
 *
 * This prevents the race condition where two requests could create the same
 * domain for the same tenant before the database unique constraint is checked.
 */
class UniqueTenantDomain implements ValidationRule
{
    public function __construct(
        protected ?int $tenantId = null,
        protected ?int $ignoreId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->tenantId === null) {
            return;
        }

        $query = TenantDomain::where('tenant_id', $this->tenantId)
            ->where('domain', $value);

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('Bu domen ushbu tenant uchun allaqachon mavjud.');
        }
    }
}
