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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->string('subject')->nullable();
            $table->enum('source', ['widget', 'telegram', 'api'])->default('widget');
            $table->string('telegram_chat_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->string('open_token')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['project_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['visitor_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('visitors')
                ->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('visitor_id');
            $table->index('last_message_at');
            $table->index('telegram_chat_id');
            $table->index(['project_id', 'visitor_id', 'status']);
            $table->unique(['id', 'tenant_id']);
            $table->unique(['project_id', 'visitor_id', 'open_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
