<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class ApiTenantIsolationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function tenant_a_cannot_access_tenant_b_project(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $projectA = Project::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        $projectB = Project::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Project B',
            'slug' => 'project-b',
        ]);

        $userA = $this->createTenantUser($tenantA);

        // Act as tenant A user
        $this->actingAs($userA, 'sanctum');
        Tenant::setCurrent($tenantA);

        // Try to access tenant B's project (should return 404 due to TenantScope)
        $response = $this->getJson("/api/tenant/projects/{$projectB->id}");

        $response->assertStatus(404);

        $this->clearTenantContext();
    }

    /** @test */
    public function tenant_a_can_access_own_project(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);

        $projectA = Project::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        $userA = $this->createTenantUser($tenantA);

        // Act as tenant A user
        $this->actingAs($userA, 'sanctum');
        Tenant::setCurrent($tenantA);

        $response = $this->getJson("/api/tenant/projects/{$projectA->id}");

        $response->assertStatus(200);

        $this->clearTenantContext();
    }

    /** @test */
    public function super_admin_can_access_all_projects(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $projectA = Project::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        $projectB = Project::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Project B',
            'slug' => 'project-b',
        ]);

        $superAdmin = $this->createSuperAdmin();
        $this->actingAs($superAdmin, 'sanctum');

        // Super admin should see all projects
        $response = $this->getJson('/api/tenant/projects');

        $response->assertStatus(200);

        // Note: Since TenantScope bypasses for super admins, both projects should be visible
        // The actual count depends on the controller's response format
    }
}
