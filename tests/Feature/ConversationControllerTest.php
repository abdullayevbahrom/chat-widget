<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
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

    protected function createConversation(array $attributes = []): Conversation
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $this->tenant->id]);

        return Conversation::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => Conversation::STATUS_OPEN,
        ], $attributes));
    }

    #[Test]
    public function guest_cannot_access_conversation_pages(): void
    {
        $this->get('/dashboard/conversations')
            ->assertRedirect('/auth/login');

        $conversation = $this->createConversation();
        $this->get('/dashboard/conversations/' . $conversation->id)
            ->assertRedirect('/auth/login');
    }

    #[Test]
    public function user_can_view_conversations_index(): void
    {
        $this->createConversation();

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations')
            ->assertOk()
            ->assertSee('Conversations')
            ->assertSee('Manage visitor conversations');
    }

    #[Test]
    public function user_can_filter_conversations_by_status(): void
    {
        $this->createConversation(['status' => Conversation::STATUS_OPEN]);
        $this->createConversation(['status' => Conversation::STATUS_CLOSED]);
        $this->createConversation(['status' => Conversation::STATUS_ARCHIVED]);

        // Filter by open status
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations?status=open')
            ->assertOk();

        // Filter by closed status
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations?status=closed')
            ->assertOk();
    }

    #[Test]
    public function user_can_filter_conversations_by_project(): void
    {
        $project1 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $project2 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $this->tenant->id]);

        Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project1->id,
            'visitor_id' => $visitor->id,
        ]);

        Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project2->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations?project_id=' . $project1->id)
            ->assertOk();
    }

    #[Test]
    public function user_can_view_conversation_show_page(): void
    {
        $conversation = $this->createConversation();

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations/' . $conversation->id)
            ->assertOk()
            ->assertSee('Conversation Details')
            ->assertSee('Messages');
    }

    #[Test]
    public function user_can_close_open_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_OPEN]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/close')
            ->assertRedirect();

        $conversation->refresh();
        $this->assertEquals(Conversation::STATUS_CLOSED, $conversation->status);
    }

    #[Test]
    public function user_can_reopen_closed_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_CLOSED]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/reopen')
            ->assertRedirect();

        $conversation->refresh();
        $this->assertEquals(Conversation::STATUS_OPEN, $conversation->status);
    }

    #[Test]
    public function user_can_archive_open_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_OPEN]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/archive')
            ->assertRedirect();

        $conversation->refresh();
        $this->assertEquals(Conversation::STATUS_ARCHIVED, $conversation->status);
    }

    #[Test]
    public function user_can_archive_closed_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_CLOSED]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/archive')
            ->assertRedirect();

        $conversation->refresh();
        $this->assertEquals(Conversation::STATUS_ARCHIVED, $conversation->status);
    }

    #[Test]
    public function cannot_close_already_closed_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_CLOSED]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/close')
            ->assertRedirect();

        $this->assertSessionHas('error');
    }

    #[Test]
    public function cannot_reopen_archived_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_ARCHIVED]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/reopen')
            ->assertRedirect();

        $this->assertSessionHas('error');
    }

    #[Test]
    public function cannot_archive_archived_conversation(): void
    {
        $conversation = $this->createConversation(['status' => Conversation::STATUS_ARCHIVED]);

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $conversation->id . '/archive')
            ->assertRedirect();

        $this->assertSessionHas('error');
    }

    #[Test]
    public function cannot_access_another_tenants_conversation(): void
    {
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherVisitor = Visitor::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherConversation = Conversation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'visitor_id' => $otherVisitor->id,
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations/' . $otherConversation->id)
            ->assertNotFound();

        $this->actingAs($this->user, 'tenant_user')
            ->patch('/dashboard/conversations/' . $otherConversation->id . '/close')
            ->assertNotFound();
    }

    #[Test]
    public function show_page_displays_visitor_information(): void
    {
        $visitor = Visitor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $conversation = $this->createConversation(['visitor_id' => $visitor->id]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations/' . $conversation->id)
            ->assertSee('John Doe')
            ->assertSee('john@example.com')
            ->assertSee('Visitor Info');
    }

    #[Test]
    public function show_page_displays_messages(): void
    {
        $conversation = $this->createConversation();

        Message::withoutEvents(function () use ($conversation) {
            Message::withoutGlobalScopes()->create([
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'sender_type' => null,
                'sender_id' => null,
                'message_type' => Message::TYPE_TEXT,
                'body' => 'Hello from visitor!',
                'direction' => Message::DIRECTION_INBOUND,
            ]);
        });

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations/' . $conversation->id)
            ->assertSee('Hello from visitor!')
            ->assertSee('Messages');
    }

    #[Test]
    public function index_shows_status_badges_with_correct_colors(): void
    {
        $this->createConversation(['status' => Conversation::STATUS_OPEN]);
        $this->createConversation(['status' => Conversation::STATUS_CLOSED]);
        $this->createConversation(['status' => Conversation::STATUS_ARCHIVED]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/conversations')
            ->assertSee('bg-green-100')  // Open badge
            ->assertSee('bg-gray-100')   // Closed badge
            ->assertSee('bg-blue-100');  // Archived badge
    }
}
