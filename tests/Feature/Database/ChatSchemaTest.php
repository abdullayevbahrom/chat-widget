<?php

namespace Tests\Feature\Database;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ChatSchemaTest — chat jadvallari mavjudligi, FK lar, unique constraint lar
 * va soft deletes ishlayotganini tekshiradi.
 */
class ChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_all_chat_tables_exist(): void
    {
        $tables = ['projects', 'project_domains', 'visitors', 'conversations', 'messages'];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist but it does not."
            );
        }
    }

    /** @test */
    public function test_visitors_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'tenant_id', 'session_id', 'ip_address_encrypted', 'user_agent',
            'referer', 'current_url', 'current_page', 'device_type', 'browser',
            'browser_version', 'platform', 'platform_version', 'language',
            'country', 'city', 'is_authenticated', 'user_id', 'first_visit_at',
            'last_visit_at', 'visit_count', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('visitors', $column),
                "Expected column [{$column}] on [visitors] table but it does not exist."
            );
        }
    }

    /** @test */
    public function test_conversations_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'tenant_id', 'project_id', 'visitor_id', 'status', 'subject',
            'source', 'telegram_chat_id', 'assigned_to', 'last_message_at',
            'open_token', 'metadata', 'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('conversations', $column),
                "Expected column [{$column}] on [conversations] table but it does not exist."
            );
        }
    }

    /** @test */
    public function test_messages_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'tenant_id', 'conversation_id', 'sender_type', 'sender_id',
            'message_type', 'body', 'attachments', 'direction', 'is_read',
            'read_at', 'telegram_message_id', 'metadata', 'created_at',
            'updated_at', 'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('messages', $column),
                "Expected column [{$column}] on [messages] table but it does not exist."
            );
        }
    }

    /** @test */
    public function test_foreign_key_violation_on_conversations_project_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Invalid project_id — should violate FK constraint
        DB::table('conversations')->insert([
            'tenant_id' => $tenant->id,
            'project_id' => 999999,
            'status' => 'open',
            'source' => 'widget',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_foreign_key_violation_on_messages_conversation_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('messages')->insert([
            'tenant_id' => $tenant->id,
            'conversation_id' => 999999,
            'message_type' => 'text',
            'body' => 'Test message',
            'direction' => 'inbound',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_unique_constraint_on_tenant_and_session_id(): void
    {
        $tenant = Tenant::factory()->create();
        Visitor::factory()->create([
            'tenant_id' => $tenant->id,
            'session_id' => 'duplicate-session-id',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Same tenant_id + session_id should violate unique constraint
        Visitor::factory()->create([
            'tenant_id' => $tenant->id,
            'session_id' => 'duplicate-session-id',
        ]);
    }

    /** @test */
    public function test_soft_deletes_on_conversations(): void
    {
        $conversation = Conversation::factory()->create();
        $this->assertSoftDeleted($conversation);

        $conversation->delete();
        $this->assertSoftDeleted($conversation);

        // Verify it's not visible in normal queries
        $this->assertEquals(0, Conversation::where('id', $conversation->id)->count());

        // Verify it's visible withTrashed
        $this->assertEquals(1, Conversation::withTrashed()->where('id', $conversation->id)->count());
    }

    /** @test */
    public function test_soft_deletes_on_messages(): void
    {
        $message = Message::factory()->create();
        $this->assertSoftDeleted($message);

        $message->delete();
        $this->assertSoftDeleted($message);

        // Verify it's not visible in normal queries
        $this->assertEquals(0, Message::where('id', $message->id)->count());

        // Verify it's visible withTrashed
        $this->assertEquals(1, Message::withTrashed()->where('id', $message->id)->count());
    }

    /** @test */
    public function test_composite_foreign_key_enforces_tenant_isolation_on_conversations(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);

        // Try to insert a conversation with tenantB's tenant_id but projectA's project_id
        // This should violate the composite FK (project_id, tenant_id) -> projects(id, tenant_id)
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('conversations')->insert([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectA->id,
            'status' => 'open',
            'source' => 'widget',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_composite_foreign_key_enforces_tenant_isolation_on_messages(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenantA->id,
            'project_id' => $projectA->id,
        ]);

        // Try to insert a message with different tenant_id than the conversation
        // This should violate the composite FK (conversation_id, tenant_id) -> conversations(id, tenant_id)
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('messages')->insert([
            'tenant_id' => $tenantB->id,
            'conversation_id' => $conversation->id,
            'message_type' => 'text',
            'body' => 'Test message',
            'direction' => 'inbound',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_cascade_delete_on_project_removes_conversations_and_messages(): void
    {
        $project = Project::factory()->create();
        $conversation = Conversation::factory()->create(['project_id' => $project->id]);
        $message = Message::factory()->create(['conversation_id' => $conversation->id]);

        $project->delete();

        $this->assertEquals(0, Conversation::withTrashed()->where('project_id', $project->id)->count());
        $this->assertEquals(0, Message::withTrashed()->where('conversation_id', $conversation->id)->count());
    }

    /** @test */
    public function test_enum_values_are_validated_for_conversation_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('conversations')->insert([
            'tenant_id' => $tenant->id,
            'project_id' => 1,
            'status' => 'invalid_status',
            'source' => 'widget',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function test_enum_values_are_validated_for_message_direction(): void
    {
        $tenant = Tenant::factory()->create();
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('messages')->insert([
            'tenant_id' => $tenant->id,
            'conversation_id' => 1,
            'message_type' => 'text',
            'body' => 'Test',
            'direction' => 'invalid_direction',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
