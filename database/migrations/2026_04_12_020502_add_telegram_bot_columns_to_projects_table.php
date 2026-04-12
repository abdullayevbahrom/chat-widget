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
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'telegram_bot_token')) {
                $table->string('telegram_bot_token', 255)->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('projects', 'telegram_bot_username')) {
                $table->string('telegram_bot_username', 100)->nullable()->after('telegram_bot_token');
            }

            if (! Schema::hasColumn('projects', 'telegram_bot_name')) {
                $table->string('telegram_bot_name', 255)->nullable()->after('telegram_bot_username');
            }

            if (! Schema::hasColumn('projects', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 100)->nullable()->after('telegram_bot_name');
            }

            if (! Schema::hasColumn('projects', 'telegram_is_active')) {
                $table->boolean('telegram_is_active')->default(false)->after('telegram_chat_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('projects', 'telegram_bot_token') ? 'telegram_bot_token' : null,
                Schema::hasColumn('projects', 'telegram_bot_username') ? 'telegram_bot_username' : null,
                Schema::hasColumn('projects', 'telegram_bot_name') ? 'telegram_bot_name' : null,
                Schema::hasColumn('projects', 'telegram_chat_id') ? 'telegram_chat_id' : null,
                Schema::hasColumn('projects', 'telegram_is_active') ? 'telegram_is_active' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
