<?php

namespace Tests\Feature\Database;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * ChatConstraintsTest — application-level constraint lar, saving event validatsiyasi,
 * tenant scope filtrlash va sender integrity tekshiruvlarini test qiladi.
 */
class ChatConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_conversation_saving_syncs_tenant_id_from_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = Conversation::factory()->make([
            'project_id' => $project->id,
        ]);

        $this->assertNull($conversation->tenant_id);

        $conversation->save();

        $this->assertEquals($tenant->id, $conversation->tenant_id);
    }

    /** @test */
    public function test_conversation_saving_sets_open_token_for_open_status(): void
    {
        $conversation = Conversation::factory()->create([
            'status' => Conversation::STATUS_OPEN,
        ]);

        $this->assertEquals(Conversation::OPEN_TOKEN_ACTIVE, $conversation->open_token);
    }

    /** @test */
    public function test_conversation_saving_clears_open_token_for_non_open_status(): void
    {
        $conversation = Conversation::factory()->create([
            'status' => Conversation::STATUS_CLOSED,
        ]);

        $this->assertNull($conversation->open_token);
    }

    /** @test */
    public function test_conversation_saving_validates_visitor_belongs_to_same_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenantB->id]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conversation visitor must belong to the same tenant as the project.');

        Conversation::factory()->create([
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
    }

    /** @test */
    public function test_conversation_saving_validates_assigned_user_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $user = User::factory()->create(['tenant_id' => $tenantB->id]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conversation assigned user must belong to the same tenant.');

        Conversation::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $user->id,
        ]);
    }

    /** @test */
    public function test_message_saving_syncs_tenant_id_from_conversation(): void
    {
        $message = Message::factory()->make();

        $this->assertNull($message->tenant_id);

        $message->save();

        $this->assertNotNull($message->tenant_id);
        $this->assertEquals($message->conversation->tenant_id, $message->tenant_id);
    }

    /** @test */
    public function test_message_created_updates_conversation_last_message_at(): void
    {
        $conversation = Conversation::factory()->create(['last_message_at' => null]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
        ]);

        $conversation->refresh();

        $this->assertEquals($message->created_at, $conversation->last_message_at);
    }

    /** @test */
    public function test_message_saving_requires_sender_for_non_system_messages(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only system or event messages may omit the sender.');

        Message::factory()->create([
            'message_type' => Message::TYPE_TEXT,
            'sender_type' => null,
            'sender_id' => null,
        ]);
    }

    /** @test */
    public function test_system_message_can_be_created_without_sender(): void
    {
        $message = Message::factory()->create([
            'message_type' => Message::TYPE_SYSTEM,
            'sender_type' => null,
            'sender_id' => null,
        ]);

        $this->assertNull($message->sender_type);
        $this->assertNull($message->sender_id);
        $this->assertEquals(Message::TYPE_SYSTEM, $message->message_type);
    }

    /** @test */
    public function test_event_message_can_be_created_without_sender(): void
    {
        $message = Message::factory()->create([
            'message_type' => Message::TYPE_EVENT,
            'sender_type' => null,
            'sender_id' => null,
        ]);

        $this->assertNull($message->sender_type);
        $this->assertNull($message->sender_id);
        $this->assertEquals(Message::TYPE_EVENT, $message->message_type);
    }

    /** @test */
    public function test_message_assert_sender_integrity_cross_tenant_visitor_fails(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenantB->id]);
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenantA->id,
            'project_id' => $project->id,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Message visitor sender must belong to the same tenant.');

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
        ]);
    }

    /** @test */
    public function test_message_assert_sender_integrity_cross_tenant_user_fails(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantB->id]);
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenantA->id,
            'project_id' => $project->id,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Message user sender must belong to the conversation tenant.');

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => User::class,
            'sender_id' => $user->id,
        ]);
    }

    /** @test */
    public function test_tenant_scope_filters_visitors_by_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $visitorA = Visitor::factory()->create(['tenant_id' => $tenantA->id]);
        $visitorB = Visitor::factory()->create(['tenant_id' => $tenantB->id]);

        // Without tenant context, TenantScope returns empty results
        $this->assertEquals(0, Visitor::count());

        // With tenantA context, only visitorA is visible
        Tenant::setCurrent($tenantA);
        $visibleVisitors = Visitor::all();

        $this->assertEquals(1, $visibleVisitors->count());
        $this->assertEquals($visitorA->id, $visibleVisitors->first()->id);

        // With tenantB context, only visitorB is visible
        Tenant::setCurrent($tenantB);
        $visibleVisitors = Visitor::all();

        $this->assertEquals(1, $visibleVisitors->count());
        $this->assertEquals($visitorB->id, $visibleVisitors->first()->id);
    }

    /** @test */
    public function test_tenant_scope_filters_conversations_by_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);

        $convA = Conversation::factory()->create(['project_id' => $projectA->id]);
        $convB = Conversation::factory()->create(['project_id' => $projectB->id]);

        // Without tenant context, returns empty
        $this->assertEquals(0, Conversation::count());

        // With tenantA context
        Tenant::setCurrent($tenantA);
        $this->assertEquals(1, Conversation::count());
        $this->assertEquals($convA->id, Conversation::first()->id);
    }

    /** @test */
    public function test_conversation_open_and_close_methods_work(): void
    {
        $conversation = Conversation::factory()->create([
            'status' => Conversation::STATUS_OPEN,
        ]);

        $this->assertTrue($conversation->isOpen());
        $this->assertFalse($conversation->isClosed());

        $conversation->close();

        $this->assertFalse($conversation->isOpen());
        $this->assertTrue($conversation->isClosed());

        $conversation->reopen();

        $this->assertTrue($conversation->isOpen());
        $this->assertFalse($conversation->isClosed());
    }

    /** @test */
    public function test_message_inbound_and_outbound_scopes(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_INBOUND,
        ]);
        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $this->assertEquals(3, Message::inbound()->count());
        $this->assertEquals(2, Message::outbound()->count());
    }

    /** @test */
    public function test_message_unread_scope(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(4)->create([
            'conversation_id' => $conversation->id,
            'is_read' => false,
        ]);
        Message::factory()->count(1)->create([
            'conversation_id' => $conversation->id,
            'is_read' => true,
        ]);

        $this->assertEquals(4, Message::unread()->count());
    }

    /** @test */
    public function test_message_of_type_scope(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'message_type' => Message::TYPE_TEXT,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => Message::TYPE_IMAGE,
        ]);

        $this->assertEquals(2, Message::ofType(Message::TYPE_TEXT)->count());
        $this->assertEquals(1, Message::ofType(Message::TYPE_IMAGE)->count());
    }

    /** @test */
    public function test_conversation_open_and_closed_scopes(): void
    {
        $project = Project::factory()->create();
        Conversation::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
        Conversation::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => Conversation::STATUS_CLOSED,
        ]);

        $this->assertEquals(3, Conversation::open()->count());
        $this->assertEquals(2, Conversation::closed()->count());
    }
}
