<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable tenant context for tests by default
        // This allows tests to create and query tenants without scope interference
        Tenant::clearCurrent();
    }

    /**
     * Set tenant context for tests that need it.
     */
    protected function setTenantContext(Tenant $tenant): void
    {
        Tenant::setCurrent($tenant);
    }

    /**
     * Clear tenant context.
     */
    protected function clearTenantContext(): void
    {
        Tenant::clearCurrent();
    }
}
