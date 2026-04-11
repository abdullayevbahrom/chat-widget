<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTenantDomainWhitelist;
use App\Http\Middleware\EnforceTenantContext;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Http\Middleware\ResetTenantContext;
use App\Http\Middleware\SetTenantContext;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class TenantMiddlewareTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function set_tenant_context_middleware_sets_tenant_for_tenant_user(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $user = $this->createTenantUser($tenant);

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $middleware = new SetTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($tenant->id, Tenant::current()?->id);

        $this->clearTenantContext();
    }

    /** @test */
    public function set_tenant_context_middleware_returns_403_for_inactive_tenant(): void
    {
        $tenant = $this->createTenant(['name' => 'Inactive Tenant', 'slug' => 'inactive-tenant', 'is_active' => false]);
        $user = $this->createTenantUser($tenant);

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $middleware = new SetTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('not active', $response->getContent());

        $this->clearTenantContext();
    }

    /** @test */
    public function enforce_tenant_context_middleware_returns_403_without_tenant(): void
    {
        $this->clearTenantContext();

        $request = Request::create('/test');

        $middleware = new EnforceTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function enforce_tenant_context_middleware_allows_with_tenant(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->actAsTenant($tenant);

        $request = Request::create('/test');

        $middleware = new EnforceTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());

        $this->clearTenantContext();
    }

    /** @test */
    public function enforce_tenant_context_middleware_allows_super_admin(): void
    {
        $this->clearTenantContext();

        $superAdmin = $this->createSuperAdmin();

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($superAdmin) {
            return $superAdmin;
        });

        $middleware = new EnforceTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function enforce_tenant_context_middleware_returns_403_for_inactive_tenant(): void
    {
        $tenant = $this->createTenant(['name' => 'Inactive Tenant', 'slug' => 'inactive-tenant', 'is_active' => false]);
        $this->actAsTenant($tenant);

        $request = Request::create('/test');

        $middleware = new EnforceTenantContext();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('not active', $response->getContent());

        $this->clearTenantContext();
    }

    /** @test */
    public function reset_tenant_context_middleware_clears_tenant(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        Tenant::setCurrent($tenant);

        $this->assertNotNull(Tenant::current());

        $request = Request::create('/test');
        $middleware = new ResetTenantContext();
        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertNull(Tenant::current());
    }

    /** @test */
    public function check_tenant_domain_whitelist_rejects_non_whitelisted_domain(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->actAsTenant($tenant);

        $request = Request::create('/test');
        $request->headers->set('HOST', 'evil-domain.com');

        $middleware = new CheckTenantDomainWhitelist();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());

        $this->clearTenantContext();
    }

    /** @test */
    public function check_tenant_domain_whitelist_allows_whitelisted_domain(): void
    {
        $tenant = $this->createTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'good-domain.com',
            'is_active' => true,
        ]);
        $this->actAsTenant($tenant);

        $request = Request::create('/test');
        $request->headers->set('HOST', 'good-domain.com');

        $middleware = new CheckTenantDomainWhitelist();
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());

        $this->clearTenantContext();
    }
}
