<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tenant testlar uchun bypass yoqamiz
        Tenant::setBypass(true);
    }

    #[Test]
    public function it_creates_tenant_with_valid_data(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'is_active' => true,
            'plan' => 'premium',
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'is_active' => true,
            'plan' => 'premium',
        ]);

        $this->assertEquals('Acme Corp', $tenant->name);
        $this->assertTrue($tenant->isActive());
    }

    #[Test]
    public function it_sets_and_gets_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Tenant::setCurrent($tenant);

        $this->assertSame($tenant, Tenant::current());
        $this->assertEquals($tenant->id, Tenant::current()->id);

        Tenant::clearCurrent();
        $this->assertNull(Tenant::current());
    }

    #[Test]
    public function it_clears_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        Tenant::clearCurrent();

        $this->assertNull(Tenant::current());
    }

    #[Test]
    public function without_tenant_context_temporarily_bypasses_scope(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $this->assertFalse(Tenant::isBypassingContext());

        $result = Tenant::withoutTenantContext(function () {
            $this->assertTrue(Tenant::isBypassingContext());

            return 'bypassed';
        });

        $this->assertEquals('bypassed', $result);
        $this->assertFalse(Tenant::isBypassingContext());
    }

    #[Test]
    public function without_tenant_context_restores_state_on_exception(): void
    {
        $this->assertFalse(Tenant::isBypassingContext());

        try {
            Tenant::withoutTenantContext(function () {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertFalse(Tenant::isBypassingContext());
    }

    #[Test]
    public function it_resolves_tenant_from_domain(): void
    {
        $tenant = Tenant::factory()->create();
        TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'https://example.com',
            'is_active' => true,
        ]);

        $found = Tenant::whereHas('domains', function ($query) {
            $query->where('domain', 'https://example.com')
                ->where('is_active', true);
        })->first();

        $this->assertNotNull($found);
        $this->assertEquals($tenant->id, $found->id);
    }

    #[Test]
    public function it_checks_if_tenant_has_domain(): void
    {
        $tenant = Tenant::factory()->create();
        TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'https://example.com',
            'is_active' => true,
        ]);

        $this->assertTrue($tenant->hasDomain('https://example.com'));
        $this->assertFalse($tenant->hasDomain('https://other.com'));
    }

    #[Test]
    public function it_gets_default_domain(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'https://acme.example.com']);

        $this->assertEquals('https://acme.example.com', $tenant->domain);
    }

    #[Test]
    public function it_has_domains_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        TenantDomain::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertEquals(3, $tenant->domains()->count());
        $this->assertInstanceOf(TenantDomain::class, $tenant->domains->first());
    }

    #[Test]
    public function it_has_users_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertEquals(3, $tenant->users()->count());
        $this->assertInstanceOf(User::class, $tenant->users->first());
    }

    #[Test]
    public function it_has_conversations_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor1 = \App\Models\Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $visitor2 = \App\Models\Visitor::factory()->create(['tenant_id' => $tenant->id]);
        \App\Models\Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor1->id,
            'status' => 'open',
        ]);
        \App\Models\Conversation::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor2->id,
        ]);

        $this->assertEquals(2, $tenant->conversations()->count());
    }

    #[Test]
    public function it_has_messages_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $tenant->messages());
    }

    #[Test]
    public function it_checks_if_tenant_is_active(): void
    {
        $activeTenant = Tenant::factory()->create(['is_active' => true]);
        $inactiveTenant = Tenant::factory()->create(['is_active' => false]);

        $this->assertTrue($activeTenant->isActive());
        $this->assertFalse($inactiveTenant->isActive());
    }

    #[Test]
    public function it_checks_if_tenant_is_subscribed(): void
    {
        $freeTenant = Tenant::factory()->create(['plan' => 'free']);
        $premiumTenant = Tenant::factory()->create(['plan' => 'premium', 'subscription_expires_at' => null]);
        $expiredTenant = Tenant::factory()->create([
            'plan' => 'premium',
            'subscription_expires_at' => now()->subDays(1),
        ]);

        $this->assertFalse($freeTenant->isSubscribed());
        $this->assertTrue($premiumTenant->isSubscribed());
        $this->assertFalse($expiredTenant->isSubscribed());
    }

    #[Test]
    public function it_checks_if_tenant_has_custom_domain(): void
    {
        $withDomain = Tenant::factory()->create(['domain' => 'https://acme.com']);
        $withoutDomain = Tenant::factory()->create(['domain' => null]);

        $this->assertTrue($withDomain->hasCustomDomain());
        $this->assertFalse($withoutDomain->hasCustomDomain());
    }

    #[Test]
    public function it_has_active_domains_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        TenantDomain::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        TenantDomain::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $this->assertEquals(1, $tenant->activeDomains()->count());
    }

    #[Test]
    public function domain_cache_returns_correct_result(): void
    {
        $tenant = Tenant::factory()->create();
        TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'https://cached-example.com',
            'is_active' => true,
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $tenant->hasDomain('https://cached-example.com');
        $this->assertTrue($result);
    }

    #[Test]
    public function it_casts_settings_to_json(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['key' => 'value']]);

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('value', $tenant->settings['key']);
    }

    #[Test]
    public function it_casts_is_active_to_boolean(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => 1]);

        $this->assertIsBool($tenant->is_active);
        $this->assertTrue($tenant->is_active);
    }
}
