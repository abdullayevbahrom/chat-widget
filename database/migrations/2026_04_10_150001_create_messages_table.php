<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->nullableMorphs('sender');
            $table->enum('message_type', ['text', 'image', 'file', 'system', 'event'])->default('text');
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['conversation_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('conversations')
                ->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'is_read']);
            $table->index('created_at');
            $table->index('message_type');
            $table->unique(['id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
