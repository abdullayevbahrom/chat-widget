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
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bot_settings', 'telegram_admin_ids')) {
                $table->json('telegram_admin_ids')->nullable()->after('chat_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_bot_settings', 'telegram_admin_ids')) {
                $table->dropColumn('telegram_admin_ids');
            }
        });
    }
};
