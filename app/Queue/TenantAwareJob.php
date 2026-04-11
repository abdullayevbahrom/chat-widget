<?php

namespace App\Queue;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for tenant-aware queue jobs.
 *
 * Automatically captures the current tenant when the job is created
 * and restores the tenant context when the job is processed.
 *
 * Usage:
 *   class MyJob extends TenantAwareJob
 *   {
 *       public function handle(): void
 *       {
 *           // Tenant::current() is available here
 *       }
 *   }
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant ID that this job belongs to.
     */
    public ?int $tenantId = null;

    /**
     * Capture the current tenant when the job is instantiated.
     */
    public function __construct()
    {
        $this->tenantId = Tenant::current()?->id;
    }

    /**
     * Set up the tenant context before the job is handled.
     *
     * @return void
     */
    public function withTenantContext(callable $callback): mixed
    {
        $previousTenant = Tenant::current();

        if ($this->tenantId !== null) {
            $tenant = Tenant::find($this->tenantId);

            if ($tenant !== null) {
                Tenant::setCurrent($tenant);
            }
        }

        try {
            return $callback();
        } finally {
            // Restore the previous tenant context (or clear it)
            if ($previousTenant !== null) {
                Tenant::setCurrent($previousTenant);
            } else {
                Tenant::clearCurrent();
            }
        }
    }
}
