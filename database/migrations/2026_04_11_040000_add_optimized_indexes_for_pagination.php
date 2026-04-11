<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CONCURRENTLY indexlar transaction ichida ishlamaydi (PostgreSQL).
     * Shuning uchun migration ni transaction tashqarisida ishga tushiramiz.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Add optimized indexes for cursor-based pagination and unread count queries.
     * Uses CONCURRENTLY for PostgreSQL to avoid table locks in production.
     */
    public function up(): void
    {
        // Index for cursor-based pagination on messages (conversation_id, id DESC)
        // This is more efficient than (conversation_id, created_at) for cursor pagination
        // since we paginate by id, not created_at.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_conv_id_desc ON messages (conversation_id, id DESC);');
        } else {
            Schema::table('messages', function (Blueprint $table): void {
                $table->index(['conversation_id', 'id'], 'idx_messages_conv_id_desc');
            });
        }

        // Composite index for unread count queries (conversation_id, is_read)
        // Already exists as (conversation_id, is_read) — verify it's being used
        // for getUnreadCount() queries. No change needed if it exists.

        // Index for polymorphic sender lookups (sender_type, sender_id)
        // Used in assertSenderIntegrity validation
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_sender ON messages (sender_type, sender_id);');
        } else {
            Schema::table('messages', function (Blueprint $table): void {
                $table->index(['sender_type', 'sender_id'], 'idx_messages_sender');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('messages', 'idx_messages_conv_id_desc')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropIndex('idx_messages_conv_id_desc');
            });
        }

        if (Schema::hasIndex('messages', 'idx_messages_sender')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropIndex('idx_messages_sender');
            });
        }
    }
};
