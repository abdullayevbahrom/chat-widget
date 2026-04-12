<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add public_id (UUID) to visitors table
        if (!Schema::hasColumn('visitors', 'public_id')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->after('id');
                $table->index('public_id');
            });

            // Backfill existing records
            $total = DB::table('visitors')->whereNull('public_id')->count();
            if ($total > 0) {
                DB::table('visitors')->whereNull('public_id')->orderBy('id')->chunk(100, function ($visitors) {
                    foreach ($visitors as $visitor) {
                        DB::table('visitors')->where('id', $visitor->id)->update([
                            'public_id' => (string) Str::uuid(),
                        ]);
                    }
                });
            }
        }

        // Add public_id (UUID) to conversations table
        if (!Schema::hasColumn('conversations', 'public_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->after('id');
                $table->index('public_id');
            });

            // Backfill existing records
            $total = DB::table('conversations')->whereNull('public_id')->count();
            if ($total > 0) {
                DB::table('conversations')->whereNull('public_id')->orderBy('id')->chunk(100, function ($conversations) {
                    foreach ($conversations as $conversation) {
                        DB::table('conversations')->where('id', $conversation->id)->update([
                            'public_id' => (string) Str::uuid(),
                        ]);
                    }
                });
            }
        }

        // Add public_id (UUID) to messages table
        if (!Schema::hasColumn('messages', 'public_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->after('id');
                $table->index('public_id');
            });

            // Backfill existing records
            $total = DB::table('messages')->whereNull('public_id')->count();
            if ($total > 0) {
                DB::table('messages')->whereNull('public_id')->orderBy('id')->chunk(100, function ($messages) {
                    foreach ($messages as $message) {
                        DB::table('messages')->where('id', $message->id)->update([
                            'public_id' => (string) Str::uuid(),
                        ]);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
