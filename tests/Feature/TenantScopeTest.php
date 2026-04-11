<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class TenantScopeTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function it_scopes_queries_to_current_tenant(): void
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

        // Act as tenant A
        $this->actAsTenant($tenantA);

        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($projectA->id, $projects->first()->id);

        // Act as tenant B
        $this->actAsTenant($tenantB);

        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($projectB->id, $projects->first()->id);

        $this->clearTenantContext();
    }

    /** @test */
    public function it_returns_empty_result_when_no_tenant_context(): void
    {
        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);

        Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        // No tenant context
        $this->clearTenantContext();

        $projects = Project::all();
        $this->assertCount(0, $projects);
    }

    /** @test */
    public function super_admin_can_see_all_data(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        Project::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Project A',
            'slug' => 'project-a',
        ]);

        Project::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Project B',
            'slug' => 'project-b',
        ]);

        $superAdmin = $this->createSuperAdmin();
        $this->actingAs($superAdmin);

        $projects = Project::all();
        $this->assertCount(2, $projects);
    }

    /** @test */
    public function it_scopes_conversations_via_tenant_scope(): void
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

        $visitorA = Visitor::create([
            'tenant_id' => $tenantA->id,
            'session_id' => 'session-a',
            'ip_address_encrypted' => 'encrypted-ip-a',
            'first_visit_at' => now(),
            'last_visit_at' => now(),
            'visit_count' => 1,
        ]);

        $visitorB = Visitor::create([
            'tenant_id' => $tenantB->id,
            'session_id' => 'session-b',
            'ip_address_encrypted' => 'encrypted-ip-b',
            'first_visit_at' => now(),
            'last_visit_at' => now(),
            'visit_count' => 1,
        ]);

        $conversationA = Conversation::create([
            'tenant_id' => $tenantA->id,
            'project_id' => $projectA->id,
            'visitor_id' => $visitorA->id,
            'status' => 'open',
            'source' => 'widget',
            'last_message_at' => now(),
        ]);

        $conversationB = Conversation::create([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectB->id,
            'visitor_id' => $visitorB->id,
            'status' => 'open',
            'source' => 'widget',
            'last_message_at' => now(),
        ]);

        // Act as tenant A
        $this->actAsTenant($tenantA);

        $conversations = Conversation::all();
        $this->assertCount(1, $conversations);
        $this->assertEquals($conversationA->id, $conversations->first()->id);

        $this->clearTenantContext();
    }

    /** @test */
    public function has_tenant_trait_auto_assigns_tenant_id(): void
    {
        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        // Create a project without explicitly setting tenant_id
        // Note: Project model doesn't use HasTenant trait yet, so we test manually
        $project = new Project();
        $project->name = 'Auto Tenant Project';
        $project->slug = 'auto-tenant-project';
        $project->save();

        $this->assertEquals($tenant->id, $project->tenant_id);

        $this->clearTenantContext();
    }
}
