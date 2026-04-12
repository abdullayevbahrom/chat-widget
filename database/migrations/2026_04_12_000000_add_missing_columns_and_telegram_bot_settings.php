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
        // Add tenant_id to conversations table
        if (!Schema::hasColumn('conversations', 'tenant_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Add open_token to conversations table
        if (!Schema::hasColumn('conversations', 'open_token')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('open_token')->nullable()->after('status');
                $table->index('open_token');
            });
        }

        // Add closed_by to conversations table
        if (!Schema::hasColumn('conversations', 'closed_by')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->onDelete('set null');
            });
        }

        // Add tenant_id to visitors table
        if (!Schema::hasColumn('visitors', 'tenant_id')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Add visitor metadata columns
        if (!Schema::hasColumn('visitors', 'device_type')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->string('device_type')->nullable()->after('user_agent');
                $table->string('browser')->nullable()->after('device_type');
                $table->string('browser_version')->nullable()->after('browser');
                $table->string('platform')->nullable()->after('browser_version');
                $table->string('platform_version')->nullable()->after('platform');
                $table->string('country')->nullable()->after('platform_version');
                $table->string('city')->nullable()->after('country');
                $table->boolean('is_authenticated')->default(false)->after('city');
                $table->foreignId('user_id')->nullable()->after('is_authenticated')->constrained('users')->onDelete('set null');
            });
        }

        // Add tenant_id to messages table
        if (!Schema::hasColumn('messages', 'tenant_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Add widget_key_hash to projects table
        if (!Schema::hasColumn('projects', 'widget_key_hash')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('widget_key_hash')->nullable()->after('telegram_is_active');
                $table->timestamp('widget_key_generated_at')->nullable()->after('widget_key_hash');
            });
        }

        // Create telegram_bot_settings table
        if (!Schema::hasTable('telegram_bot_settings')) {
            Schema::create('telegram_bot_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->text('bot_token_encrypted')->nullable();
                $table->string('bot_username')->nullable();
                $table->string('bot_name')->nullable();
                $table->string('chat_id')->nullable();
                $table->string('webhook_secret')->nullable();
                $table->boolean('is_active')->default(false);
                $table->json('allowed_admin_ids')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_settings');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['widget_key_hash', 'widget_key_generated_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'user_id')) {
                $table->dropForeign(['user_id']);
            }
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'device_type', 'browser', 'browser_version', 'platform', 'platform_version', 'country', 'city', 'is_authenticated', 'user_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'closed_by')) {
                $table->dropForeign(['closed_by']);
            }
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['open_token']);
            $table->dropColumn(['tenant_id', 'open_token', 'closed_by']);
        });
    }
};
