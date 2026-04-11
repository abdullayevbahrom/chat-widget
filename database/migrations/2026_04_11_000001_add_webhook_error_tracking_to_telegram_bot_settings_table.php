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
            $table->json('last_webhook_error')->nullable()->after('last_webhook_status');
            $table->timestamp('last_webhook_error_at')->nullable()->after('last_webhook_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            $table->dropColumn(['last_webhook_error', 'last_webhook_error_at']);
        });
    }
};
