<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_type_constants(): void
    {
        $this->assertEquals('text', Message::TYPE_TEXT);
        $this->assertEquals('image', Message::TYPE_IMAGE);
        $this->assertEquals('file', Message::TYPE_FILE);
        $this->assertEquals('system', Message::TYPE_SYSTEM);
        $this->assertEquals('event', Message::TYPE_EVENT);
    }

    #[Test]
    public function it_has_direction_constants(): void
    {
        $this->assertEquals('inbound', Message::DIRECTION_INBOUND);
        $this->assertEquals('outbound', Message::DIRECTION_OUTBOUND);
    }

    #[Test]
    public function it_has_conversation_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertInstanceOf(Conversation::class, $message->conversation);
    }

    #[Test]
    public function it_has_sender_morph_to_relationship(): void
    {
        $message = Message::factory()->make();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphTo::class,
            $message->sender()
        );
    }

    #[Test]
    public function it_checks_if_inbound(): void
    {
        $inbound = Message::factory()->make(['direction' => 'inbound']);
        $outbound = Message::factory()->make(['direction' => 'outbound']);

        $this->assertTrue($inbound->isInbound());
        $this->assertFalse($outbound->isInbound());
    }

    #[Test]
    public function it_checks_if_outbound(): void
    {
        $outbound = Message::factory()->make(['direction' => 'outbound']);
        $inbound = Message::factory()->make(['direction' => 'inbound']);

        $this->assertTrue($outbound->isOutbound());
        $this->assertFalse($inbound->isOutbound());
    }

    #[Test]
    public function it_marks_as_read(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'is_read' => false,
        ]);

        $message->markAsRead();

        $this->assertTrue($message->is_read);
        $this->assertNotNull($message->read_at);
    }

    #[Test]
    public function it_checks_for_attachments(): void
    {
        $messageWithAttachments = Message::factory()->make([
            'attachments' => [['name' => 'file.pdf']],
        ]);
        $messageWithoutAttachments = Message::factory()->make([
            'attachments' => null,
        ]);

        $this->assertTrue($messageWithAttachments->hasAttachments());
        $this->assertFalse($messageWithoutAttachments->hasAttachments());
    }

    #[Test]
    public function it_has_scopes(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'is_read' => false,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'direction' => 'outbound',
            'is_read' => true,
        ]);

        Tenant::setCurrent($tenant);

        $this->assertEquals(1, Message::withoutGlobalScopes()->inbound()->count());
        $this->assertEquals(1, Message::withoutGlobalScopes()->outbound()->count());
        $this->assertEquals(1, Message::withoutGlobalScopes()->unread()->count());
    }

    #[Test]
    public function it_scopes_by_message_type(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'message_type' => 'system',
        ]);

        Tenant::setCurrent($tenant);

        $this->assertEquals(1, Message::withoutGlobalScopes()->ofType('system')->count());
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
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
        ]);

        $message->delete();

        $this->assertSoftDeleted($message);
    }

    #[Test]
    public function it_casts_attachments_to_array(): void
    {
        $message = Message::factory()->make([
            'attachments' => [['name' => 'test.pdf', 'size' => 1024]],
        ]);

        $this->assertIsArray($message->attachments);
        $this->assertEquals('test.pdf', $message->attachments[0]['name']);
    }

    #[Test]
    public function it_casts_metadata_to_array(): void
    {
        $message = Message::factory()->make([
            'metadata' => ['source' => 'widget'],
        ]);

        $this->assertIsArray($message->metadata);
    }

    #[Test]
    public function it_casts_read_at_to_datetime(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'read_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $message->read_at);
    }

    #[Test]
    public function it_casts_is_read_to_boolean(): void
    {
        $message = Message::factory()->make(['is_read' => 1]);

        $this->assertIsBool($message->is_read);
        $this->assertTrue($message->is_read);
    }
}
