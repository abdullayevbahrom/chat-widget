<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'telegram_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('telegram_user_id')->nullable()->after('email');
                $table->index('telegram_user_id');
            });
        }

        if (! Schema::hasColumn('visitors', 'name')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->string('name')->nullable()->after('project_id');
            });
        }

        if (! Schema::hasColumn('visitors', 'email')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->string('email')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('visitors', 'privacy_accepted_at')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->timestamp('privacy_accepted_at')->nullable()->after('email');
            });
        }

        if (! Schema::hasTable('telegram_message_references')) {
            Schema::create('telegram_message_references', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('chat_id', 100);
                $table->unsignedBigInteger('telegram_message_id');
                $table->timestamps();

                $table->unique(['chat_id', 'telegram_message_id']);
                $table->index(['tenant_id', 'project_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_message_references');

        if (Schema::hasColumn('visitors', 'privacy_accepted_at')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn('privacy_accepted_at');
            });
        }

        if (Schema::hasColumn('visitors', 'email')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        if (Schema::hasColumn('visitors', 'name')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        if (Schema::hasColumn('users', 'telegram_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['telegram_user_id']);
                $table->dropColumn('telegram_user_id');
            });
        }
    }
};
