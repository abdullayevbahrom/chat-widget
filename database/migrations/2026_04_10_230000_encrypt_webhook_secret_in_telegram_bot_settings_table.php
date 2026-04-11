<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bot_settings', 'webhook_secret_encrypted')) {
                $table->text('webhook_secret_encrypted')->nullable()->after('webhook_url');
            }
        });

        // Migrate existing webhook_secret values to webhook_secret_encrypted (encrypted)
        $rows = DB::table('telegram_bot_settings')
            ->whereNotNull('webhook_secret')
            ->get(['id', 'webhook_secret']);

        foreach ($rows as $row) {
            if ($row->webhook_secret !== null && $row->webhook_secret !== '') {
                DB::table('telegram_bot_settings')
                    ->where('id', $row->id)
                    ->update([
                        'webhook_secret_encrypted' => Crypt::encryptString($row->webhook_secret),
                        'updated_at' => now(),
                    ]);
            }
        }

        // Now drop the old column
        if (Schema::hasColumn('telegram_bot_settings', 'webhook_secret')) {
            Schema::table('telegram_bot_settings', function (Blueprint $table) {
                $table->dropColumn('webhook_secret');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bot_settings', 'webhook_secret')) {
                $table->string('webhook_secret')->nullable()->after('webhook_url');
            }
        });

        // Decrypt webhook_secret_encrypted back to webhook_secret
        $rows = DB::table('telegram_bot_settings')
            ->whereNotNull('webhook_secret_encrypted')
            ->get(['id', 'webhook_secret_encrypted']);

        foreach ($rows as $row) {
            if ($row->webhook_secret_encrypted !== null && $row->webhook_secret_encrypted !== '') {
                try {
                    $decrypted = Crypt::decryptString($row->webhook_secret_encrypted);
                    DB::table('telegram_bot_settings')
                        ->where('id', $row->id)
                        ->update([
                            'webhook_secret' => $decrypted,
                            'updated_at' => now(),
                        ]);
                } catch (Exception $e) {
                    // Skip rows that can't be decrypted
                }
            }
        }

        if (Schema::hasColumn('telegram_bot_settings', 'webhook_secret_encrypted')) {
            Schema::table('telegram_bot_settings', function (Blueprint $table) {
                $table->dropColumn('webhook_secret_encrypted');
            });
        }
    }
};
