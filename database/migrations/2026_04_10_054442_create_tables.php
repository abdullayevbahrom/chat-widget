<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('plan')->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('telegram_bot_token', 255)->nullable();
            $table->string('telegram_bot_username', 100)->nullable();
            $table->string('telegram_bot_name', 255)->nullable();
            $table->string('telegram_chat_id', 100)->nullable();
            $table->boolean('telegram_is_active')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('session_id');
            $table->text('ip_address_encrypted')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->text('current_url')->nullable();
            $table->string('current_page', 500)->nullable();
            $table->string('language', 20)->nullable();
            $table->timestamp('first_visit_at');
            $table->timestamp('last_visit_at');
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamps();

            $table->unique(['id', 'project_id']);
            $table->unique(['project_id', 'session_id']);
            $table->index('last_visit_at');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->string('subject')->nullable();
            $table->enum('source', ['widget', 'telegram', 'api'])->default('widget');
            $table->string('telegram_chat_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index('visitor_id');
            $table->index('last_message_at');
            $table->index('telegram_chat_id');
            $table->index(['project_id', 'visitor_id', 'status']);
            $table->unique(['project_id', 'visitor_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
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

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'is_read']);
            $table->index('created_at');
            $table->index('message_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('tenants');
    }
};
