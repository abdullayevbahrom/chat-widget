<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear tenant context for model tests
        Tenant::clearCurrent();
    }

    #[Test]
    public function it_has_status_constants(): void
    {
        $this->assertEquals('open', Conversation::STATUS_OPEN);
        $this->assertEquals('closed', Conversation::STATUS_CLOSED);
        $this->assertEquals('archived', Conversation::STATUS_ARCHIVED);
    }

    #[Test]
    public function it_has_source_constants(): void
    {
        $this->assertEquals('widget', Conversation::SOURCE_WIDGET);
        $this->assertEquals('telegram', Conversation::SOURCE_TELEGRAM);
        $this->assertEquals('api', Conversation::SOURCE_API);
    }

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->assertInstanceOf(Tenant::class, $conversation->tenant);
        $this->assertEquals($tenant->id, $conversation->tenant->id);
    }

    #[Test]
    public function it_has_visitor_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);
        
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->assertInstanceOf(Visitor::class, $conversation->visitor);
    }

    #[Test]
    public function it_has_messages_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);
        
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'closed',
        ]);
        // Create system messages which don't require a sender
        \App\Models\Message::withoutEvents(function () use ($conversation, $tenant) {
            \App\Models\Message::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'system',
                'body' => 'System message 1',
                'direction' => 'outbound',
                'is_read' => true,
            ]);
            \App\Models\Message::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'system',
                'body' => 'System message 2',
                'direction' => 'outbound',
                'is_read' => true,
            ]);
            \App\Models\Message::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'system',
                'body' => 'System message 3',
                'direction' => 'outbound',
                'is_read' => true,
            ]);
        });

        $this->assertEquals(3, $conversation->messages()->count());
    }

    #[Test]
    public function it_has_assignee_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $conversation->assignedUser);
    }

    #[Test]
    public function it_checks_if_open(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        $open = Conversation::factory()->make(['status' => 'open']);
        $closed = Conversation::factory()->make(['status' => 'closed']);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($closed->isOpen());
    }

    #[Test]
    public function it_checks_if_closed(): void
    {
        $closed = Conversation::factory()->make(['status' => 'closed']);
        $open = Conversation::factory()->make(['status' => 'open']);

        $this->assertTrue($closed->isClosed());
        $this->assertFalse($open->isClosed());
    }

    #[Test]
    public function it_checks_if_archived(): void
    {
        $archived = Conversation::factory()->make(['status' => 'archived']);
        $open = Conversation::factory()->make(['status' => 'open']);

        $this->assertTrue($archived->isArchived());
        $this->assertFalse($open->isArchived());
    }

    #[Test]
    public function it_can_transition_from_open_to_closed(): void
    {
        $this->assertTrue(
            (new Conversation(['status' => 'open']))->canTransitionTo('closed')
        );
    }

    #[Test]
    public function it_can_transition_from_open_to_archived(): void
    {
        $this->assertTrue(
            (new Conversation(['status' => 'open']))->canTransitionTo('archived')
        );
    }

    #[Test]
    public function it_can_transition_from_closed_to_open(): void
    {
        $this->assertTrue(
            (new Conversation(['status' => 'closed']))->canTransitionTo('open')
        );
    }

    #[Test]
    public function it_can_transition_from_closed_to_archived(): void
    {
        $this->assertTrue(
            (new Conversation(['status' => 'closed']))->canTransitionTo('archived')
        );
    }

    #[Test]
    public function it_cannot_transition_from_archived(): void
    {
        $archived = new Conversation(['status' => 'archived']);

        $this->assertFalse($archived->canTransitionTo('open'));
        $this->assertFalse($archived->canTransitionTo('closed'));
        $this->assertFalse($archived->canTransitionTo('archived'));
    }

    #[Test]
    public function it_cannot_transition_deleted_conversation(): void
    {
        $conversation = Conversation::factory()->make(['status' => 'open']);
        $conversation->deleted_at = now();

        $this->assertFalse($conversation->canTransitionTo('closed'));
    }

    #[Test]
    public function it_has_scopes(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor1 = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $visitor2 = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor1->id,
            'status' => 'open',
        ]);
        Conversation::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor2->id,
        ]);

        Tenant::setCurrent($tenant);

        $this->assertEquals(1, Conversation::withoutGlobalScopes()->open()->count());
        $this->assertEquals(1, Conversation::withoutGlobalScopes()->closed()->count());
    }

    #[Test]
    public function it_has_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $conversation->delete();

        $this->assertSoftDeleted($conversation);
        $this->assertEquals(0, Conversation::count());
        $this->assertEquals(1, Conversation::withTrashed()->count());
    }

    #[Test]
    public function it_gets_unread_count(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'closed',
        ]);
        // Create system messages which don't require a sender
        \App\Models\Message::withoutEvents(function () use ($conversation, $tenant) {
            \App\Models\Message::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'system',
                'body' => 'Unread system message',
                'direction' => 'outbound',
                'is_read' => false,
            ]);
            \App\Models\Message::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'system',
                'body' => 'Read system message',
                'direction' => 'outbound',
                'is_read' => true,
            ]);
        });

        $this->assertEquals(1, $conversation->getUnreadCount());
    }

    #[Test]
    public function it_casts_metadata_to_array(): void
    {
        $conversation = Conversation::factory()->make(['metadata' => ['key' => 'value']]);

        $this->assertIsArray($conversation->metadata);
    }

    #[Test]
    public function it_casts_dates_to_datetime(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'last_message_at' => now(),
            'closed_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $conversation->last_message_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $conversation->closed_at);
    }

    #[Test]
    public function closed_at_and_closed_by_are_set_on_close(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'open',
        ]);

        // Mock the event dispatcher to prevent broadcasting issues in tests
        \Illuminate\Support\Facades\Event::fake();

        $conversation->close();

        $this->assertNotNull($conversation->closed_at);
        $this->assertNull($conversation->closed_by);
    }
}
