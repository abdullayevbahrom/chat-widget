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
            $table->string('telegram_bot_token')->nullable()->after('is_active');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->string('telegram_bot_name')->nullable()->after('telegram_bot_username');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_name');
            $table->boolean('telegram_is_active')->default(false)->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_bot_name',
                'telegram_chat_id',
                'telegram_is_active',
            ]);
        });
    }
};
