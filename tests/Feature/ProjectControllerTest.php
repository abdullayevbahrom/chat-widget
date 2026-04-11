<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
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
    public function guest_cannot_access_project_pages(): void
    {
        $this->get('/dashboard/projects')
            ->assertRedirect('/auth/login');

        $this->get('/dashboard/projects/create')
            ->assertRedirect('/auth/login');
    }

    #[Test]
    public function user_can_view_projects_index(): void
    {
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/projects')
            ->assertOk()
            ->assertSee('Test Project')
            ->assertSee('New Project');
    }

    #[Test]
    public function user_can_view_create_form(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/projects/create')
            ->assertOk()
            ->assertSee('Create Project')
            ->assertSee('Project Name')
            ->assertSee('Theme')
            ->assertSee('Position');
    }

    #[Test]
    public function user_can_create_project(): void
    {
        $projectData = [
            'name' => 'My New Widget',
            'theme' => 'dark',
            'position' => 'bottom-left',
            'width' => 450,
            'height' => 700,
            'primary_color' => '#3b82f6',
            'custom_css' => '.widget { color: red; }',
            'is_active' => 1,
        ];

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', $projectData)
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'tenant_id' => $this->tenant->id,
            'name' => 'My New Widget',
            'is_active' => true,
        ]);

        $project = Project::where('name', 'My New Widget')->first();
        $this->assertNotNull($project);
        $this->assertEquals('dark', $project->settings['widget']['theme']);
        $this->assertEquals('bottom-left', $project->settings['widget']['position']);
        $this->assertEquals(450, $project->settings['widget']['width']);
        $this->assertEquals(700, $project->settings['widget']['height']);
        $this->assertEquals('#3b82f6', $project->settings['widget']['primary_color']);
        $this->assertTrue($project->hasWidgetKey());
    }

    #[Test]
    public function create_requires_valid_name(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => '',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function create_requires_valid_theme(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'invalid',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('theme');
    }

    #[Test]
    public function create_requires_valid_position(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'invalid',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('position');
    }

    #[Test]
    public function create_requires_valid_width(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 100, // too small
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('width');

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 900, // too large
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('width');
    }

    #[Test]
    public function create_requires_valid_height(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 100, // too small
                'primary_color' => '#6366f1',
            ])
            ->assertSessionHasErrors('height');
    }

    #[Test]
    public function create_requires_valid_primary_color(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => 'not-a-color',
            ])
            ->assertSessionHasErrors('primary_color');

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Test',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#GGG', // invalid hex
            ])
            ->assertSessionHasErrors('primary_color');
    }

    #[Test]
    public function user_can_view_edit_form(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Edit Me',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/projects/' . $project->id . '/edit')
            ->assertOk()
            ->assertSee('Edit Project')
            ->assertSee('Edit Me');
    }

    #[Test]
    public function user_can_update_project(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'settings' => [
                'widget' => [
                    'theme' => 'light',
                    'position' => 'bottom-right',
                    'width' => 400,
                    'height' => 600,
                    'primary_color' => '#6366f1',
                    'custom_css' => '',
                ],
            ],
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'theme' => 'dark',
            'position' => 'top-left',
            'width' => 500,
            'height' => 800,
            'primary_color' => '#ef4444',
            'custom_css' => '',
            'is_active' => 0,
        ];

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/projects/' . $project->id, $updatedData)
            ->assertRedirect();

        $project->refresh();

        $this->assertEquals('Updated Name', $project->name);
        $this->assertEquals('dark', $project->settings['widget']['theme']);
        $this->assertEquals('top-left', $project->settings['widget']['position']);
        $this->assertEquals(500, $project->settings['widget']['width']);
        $this->assertEquals(800, $project->settings['widget']['height']);
        $this->assertEquals('#ef4444', $project->settings['widget']['primary_color']);
        $this->assertFalse($project->is_active);
    }

    #[Test]
    public function user_can_delete_project(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete Me',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/projects/' . $project->id)
            ->assertRedirect('/dashboard/projects');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[Test]
    public function user_can_regenerate_widget_key(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Key Test',
        ]);

        // Generate initial key
        $project->generateWidgetKey();
        $oldHash = $project->widget_key_hash;

        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects/' . $project->id . '/regenerate-key')
            ->assertRedirect();

        $project->refresh();

        $this->assertNotEquals($oldHash, $project->widget_key_hash);
        $this->assertNotNull($project->widget_key_hash);
    }

    #[Test]
    public function cannot_access_another_tenants_project(): void
    {
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Project',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/projects/' . $otherProject->id . '/edit')
            ->assertNotFound();

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/projects/' . $otherProject->id, [
                'name' => 'Hacked',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
            ])
            ->assertNotFound();

        $this->actingAs($this->user, 'tenant_user')
            ->delete('/dashboard/projects/' . $otherProject->id)
            ->assertNotFound();
    }

    #[Test]
    public function project_created_with_default_active_status(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->post('/dashboard/projects', [
                'name' => 'Default Active',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
            ]);

        $project = Project::where('name', 'Default Active')->first();
        $this->assertTrue($project->is_active);
    }
}
