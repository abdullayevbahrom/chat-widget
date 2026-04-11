<?php

namespace Tests\Unit;

use App\Events\WidgetMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetMessageSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_with_returns_body_as_is_from_database(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        // Body already sanitized by resolveTelegramBody
        $sanitizedBody = '&lt;script&gt;alert(1)&lt;/script&gt; Hello World';

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => $sanitizedBody,
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);
        $broadcastData = $event->broadcastWith();

        // Body should be returned as-is, not re-stripped
        $this->assertEquals($sanitizedBody, $broadcastData['message']['body']);
        $this->assertEquals('admin', $broadcastData['message']['type']);
        $this->assertEquals($message->id, $broadcastData['message']['id']);
        $this->assertEquals($conversation->id, $broadcastData['conversation_id']);
    }

    public function test_broadcast_with_truncates_long_body(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        // Create a body longer than 5000 characters
        $longBody = str_repeat('a', 6000);

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => $longBody,
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);
        $broadcastData = $event->broadcastWith();

        $this->assertStringEndsWith('…', $broadcastData['message']['body']);
        $this->assertEquals(5001, mb_strlen($broadcastData['message']['body'])); // 5000 chars + ellipsis
    }

    public function test_broadcast_with_returns_null_for_null_body(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_IMAGE,
            'body' => null,
            'attachments' => [
                [
                    'id' => 'test-1',
                    'original_name' => 'photo.jpg',
                    'mime_type' => 'image/jpeg',
                    'size' => 12345,
                    'url' => 'https://example.com/photo.jpg',
                ],
            ],
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);
        $broadcastData = $event->broadcastWith();

        $this->assertNull($broadcastData['message']['body']);
        $this->assertCount(1, $broadcastData['message']['attachments']);
        $this->assertEquals('photo.jpg', $broadcastData['message']['attachments'][0]['original_name']);
    }

    public function test_broadcast_as_returns_widget_message_sent(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Test message',
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);

        $this->assertEquals('widget.message-sent', $event->broadcastAs());
    }

    public function test_broadcast_on_returns_correct_channels(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Test message',
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-tenant.'.$tenant->id.'.conversations', $channels[0]->name);
        $this->assertEquals('private-widget.conversation.'.$conversation->id, $channels[1]->name);
    }

    public function test_broadcast_with_includes_agent_name_when_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Test message',
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message, 'John Doe');
        $broadcastData = $event->broadcastWith();

        $this->assertEquals('John Doe', $broadcastData['agent_name']);
    }

    public function test_broadcast_with_includes_null_agent_name_when_not_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->for($project)->for($visitor)->create();

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'sender_type' => $tenant->getMorphClass(),
            'sender_id' => $tenant->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Test message',
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);

        $event = new WidgetMessageSent($conversation, $message);
        $broadcastData = $event->broadcastWith();

        $this->assertNull($broadcastData['agent_name']);
    }
}
