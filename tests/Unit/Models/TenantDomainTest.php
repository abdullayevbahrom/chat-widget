<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDomainTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $domain = TenantDomain::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $domain->tenant);
        $this->assertEquals($tenant->id, $domain->tenant->id);
    }

    #[Test]
    public function it_checks_if_domain_is_valid(): void
    {
        $tenant = Tenant::factory()->create();

        $activeDomain = TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $inactiveDomain = TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        $this->assertTrue($activeDomain->isValid());
        $this->assertFalse($inactiveDomain->isValid());
    }

    #[Test]
    public function it_casts_is_active_to_boolean(): void
    {
        $tenant = Tenant::factory()->create();
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => 1,
        ]);

        $this->assertIsBool($domain->is_active);
    }

    #[Test]
    public function it_creates_with_fillable_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'https://example.com',
            'is_active' => true,
            'notes' => 'Production domain',
        ]);

        $this->assertEquals('https://example.com', $domain->domain);
        $this->assertTrue($domain->is_active);
        $this->assertEquals('Production domain', $domain->notes);
    }

    #[Test]
    public function it_can_be_inactive(): void
    {
        $tenant = Tenant::factory()->create();
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        $this->assertFalse($domain->is_active);
    }

    #[Test]
    public function it_has_factory(): void
    {
        $domain = TenantDomain::factory()->create();

        $this->assertInstanceOf(TenantDomain::class, $domain);
        $this->assertNotNull($domain->tenant_id);
        $this->assertNotNull($domain->domain);
    }
}
