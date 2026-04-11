<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDomainControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Set tenant context for global scope
        Tenant::setCurrent($this->tenant);
    }

    protected function tearDown(): void
    {
        Tenant::clearCurrent();
        parent::tearDown();
    }

    #[Test]
    public function guest_cannot_access_domain_pages(): void
    {
        $this->get('/dashboard/tenant-domains')
            ->assertRedirect('/auth/login');

        $this->get('/dashboard/tenant-domains/create')
            ->assertRedirect('/auth/login');
    }

    #[Test]
    public function user_can_view_domains_index(): void
    {
        TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/tenant-domains')
            ->assertOk()
            ->assertSee('example.com')
            ->assertSee('Add Domain');
    }

    #[Test]
    public function user_can_view_create_form(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/tenant-domains/create')
            ->assertOk()
            ->assertSee('Add New Domain')
            ->assertSee('Domain Name');
    }

    #[Test]
    public function user_can_store_new_domain(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains', [
                'domain' => 'example.com',
                'is_active' => 1,
                'notes' => 'Production domain',
            ])
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_domains', [
            'domain' => 'example.com',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function domain_requires_valid_domain_format(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains', [
                'domain' => 'invalid-domain',
            ])
            ->assertSessionHasErrors('domain');
    }

    #[Test]
    public function domain_must_be_unique(): void
    {
        TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains', [
                'domain' => 'example.com',
            ])
            ->assertSessionHasErrors('domain');
    }

    #[Test]
    public function user_can_view_edit_form(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/tenant-domains/'.$domain->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Domain')
            ->assertSee('example.com');
    }

    #[Test]
    public function user_can_update_domain(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-domains/'.$domain->id, [
                'domain' => 'updated-example.com',
                'is_active' => 0,
                'notes' => 'Updated notes',
            ])
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domain->id,
            'domain' => 'updated-example.com',
            'is_active' => false,
            'notes' => 'Updated notes',
        ]);
    }

    #[Test]
    public function user_can_delete_domain(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/tenant-domains/'.$domain->id)
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tenant_domains', [
            'id' => $domain->id,
        ]);
    }

    #[Test]
    public function user_can_verify_domain(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
            'is_verified' => false,
            'verification_token' => null,
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains/'.$domain->id.'/verify')
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('success');

        $domain->refresh();
        $this->assertNotNull($domain->verification_token);
    }

    #[Test]
    public function verified_domain_shows_already_verified_message(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains/'.$domain->id.'/verify')
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('error', 'This domain is already verified.');
    }

    #[Test]
    public function user_can_reverify_domain(): void
    {
        $domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
            'is_verified' => false,
            'verification_token' => 'old-token',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains/'.$domain->id.'/reverify')
            ->assertRedirect('/dashboard/tenant-domains')
            ->assertSessionHas('success');

        $domain->refresh();
        $this->assertNotEquals('old-token', $domain->verification_token);
        $this->assertNotNull($domain->verification_token);
    }

    #[Test]
    public function stored_domain_is_normalized_to_lowercase(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains', [
                'domain' => 'EXAMPLE.COM',
            ])
            ->assertRedirect('/dashboard/tenant-domains');

        $this->assertDatabaseHas('tenant_domains', [
            'domain' => 'example.com',
        ]);
    }

    #[Test]
    public function store_generates_verification_token(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/tenant-domains', [
                'domain' => 'example.com',
            ]);

        $this->assertDatabaseHas('tenant_domains', [
            'domain' => 'example.com',
            'is_verified' => false,
        ]);

        $domain = TenantDomain::where('domain', 'example.com')->first();
        $this->assertNotNull($domain->verification_token);
    }
}
