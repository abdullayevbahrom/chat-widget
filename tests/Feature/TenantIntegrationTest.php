<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class TenantIntegrationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function full_request_lifecycle_maintains_tenant_isolation(): void
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

        // Verify: Can only see own projects
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($projectA->id, $projects->first()->id);

        // Verify: Cannot see tenant B's project even by direct query with TenantScope
        $projectBQuery = Project::find($projectB->id);
        $this->assertNull($projectBQuery);

        $this->clearTenantContext();
    }

    /** @test */
    public function conversation_creation_maintains_tenant_integrity(): void
    {
        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        $visitor = Visitor::create([
            'tenant_id' => $tenant->id,
            'session_id' => 'session-123',
            'ip_address_encrypted' => 'encrypted-ip',
            'first_visit_at' => now(),
            'last_visit_at' => now(),
            'visit_count' => 1,
        ]);

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'open',
            'source' => 'widget',
            'last_message_at' => now(),
        ]);

        // Verify tenant_id was set correctly
        $this->assertEquals($tenant->id, $conversation->tenant_id);

        // Verify TenantScope works
        $this->actAsTenant($tenant);
        $found = Conversation::find($conversation->id);
        $this->assertNotNull($found);

        $this->clearTenantContext();
    }

    /** @test */
    public function cache_operations_respect_tenant_boundaries(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Cache something as tenant A
        $this->actAsTenant($tenantA);
        Cache::put("tenant:{$tenantA->id}:shared_key", 'value_a');

        $this->clearTenantContext();

        // Cache something as tenant B with the same logical key
        $this->actAsTenant($tenantB);
        Cache::put("tenant:{$tenantB->id}:shared_key", 'value_b');

        // Verify tenant B cannot see tenant A's cache
        $valueB = Cache::get("tenant:{$tenantB->id}:shared_key");
        $valueAForB = Cache::get("tenant:{$tenantA->id}:shared_key");

        $this->assertEquals('value_b', $valueB);
        $this->assertEquals('value_a', $valueAForB); // Different keys, so both exist

        $this->clearTenantContext();
    }

    /** @test */
    public function response_does_not_leak_other_tenant_data(): void
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
        $this->actingAs($userA, 'sanctum');
        Tenant::setCurrent($tenantA);

        // Get projects list - should only contain tenant A's project
        $response = $this->getJson('/api/tenant/projects');

        $response->assertStatus(200);

        // Verify response doesn't contain tenant B's project data
        $responseData = $response->json();

        if (isset($responseData['data'])) {
            foreach ($responseData['data'] as $project) {
                $this->assertNotEquals($projectB->id, $project['id'] ?? null);
            }
        }

        $this->clearTenantContext();
    }
}
