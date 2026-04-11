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
            $table->text('bot_token_encrypted')->nullable()->change();
            $table->text('webhook_secret_encrypted')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            $table->text('bot_token_encrypted')->nullable(false)->change();
            $table->text('webhook_secret_encrypted')->nullable(false)->change();
        });
    }
};
