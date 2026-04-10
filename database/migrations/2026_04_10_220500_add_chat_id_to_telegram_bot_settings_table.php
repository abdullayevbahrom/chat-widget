<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bot_settings', 'chat_id')) {
                $table->string('chat_id')->nullable()->after('bot_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_bot_settings', 'chat_id')) {
                $table->dropColumn('chat_id');
            }
        });
    }
};
