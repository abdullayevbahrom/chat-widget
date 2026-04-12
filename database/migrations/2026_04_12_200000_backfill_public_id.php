<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfill public_id for any existing records that are missing it.
     * This ensures all records have UUIDs even if the previous migration's
     * backfill didn't run correctly.
     */
    public function up(): void
    {
        $this->backfillTable('visitors');
        $this->backfillTable('conversations');
        $this->backfillTable('messages');
    }

    protected function backfillTable(string $table): void
    {
        $count = DB::table($table)->whereNull('public_id')->count();

        if ($count > 0) {
            DB::table($table)->whereNull('public_id')->orderBy('id')->chunk(500, function ($records) use ($table) {
                foreach ($records as $record) {
                    DB::table($table)->where('id', $record->id)->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
                }
            });

            $remaining = DB::table($table)->whereNull('public_id')->count();
            echo "Backfilled {$table}: {$count} records processed, {$remaining} remaining.\n";
        } else {
            echo "{$table}: All records already have public_id.\n";
        }
    }

    public function down(): void
    {
        // No-op - we don't want to remove public_id values
    }
};
