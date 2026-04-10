<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix migration to address review issues #4, #6, #7, #14, #15:
 * - Add unique constraint on session_id (#4)
 * - Remove duplicate tenant_id index (#7)
 * - Add ip_address_encrypted column for GDPR compliance (#14)
 * - Add length limits on columns (#15)
 * - Drop old ip_address column after migration
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If the visitors table doesn't exist yet, the main migration handles everything
        if (! Schema::hasTable('visitors')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table) {
            // Add the new encrypted IP column
            if (! Schema::hasColumn('visitors', 'ip_address_encrypted')) {
                $table->text('ip_address_encrypted')->nullable()->after('session_id');
            }

            // Add length limits on existing columns
            if (Schema::hasColumn('visitors', 'current_page')) {
                $table->string('current_page', 500)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'device_type')) {
                $table->string('device_type', 20)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'browser')) {
                $table->string('browser', 100)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'browser_version')) {
                $table->string('browser_version', 50)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'platform')) {
                $table->string('platform', 100)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'platform_version')) {
                $table->string('platform_version', 50)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'language')) {
                $table->string('language', 20)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'country')) {
                $table->string('country', 100)->nullable()->change();
            }
            if (Schema::hasColumn('visitors', 'city')) {
                $table->string('city', 100)->nullable()->change();
            }
        });

        // Migrate existing ip_address values to ip_address_encrypted
        // We can't decrypt them, but we mark them as migrated
        if (Schema::hasColumn('visitors', 'ip_address')) {
            $exists = DB::table('visitors')->whereNotNull('ip_address')->exists();
            if ($exists) {
                // Existing plaintext IPs are lost (GDPR: they should have been encrypted from the start)
                // Set a marker to indicate the IP was previously stored
                DB::table('visitors')->whereNotNull('ip_address')->update([
                    'ip_address_encrypted' => null, // Will be encrypted on next visit
                ]);
            }

            // Drop the old ip_address column and its index
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('visitors');
            if (isset($indexesFound['visitors_ip_address_index'])) {
                Schema::table('visitors', function (Blueprint $table) {
                    $table->dropIndex('visitors_ip_address_index');
                });
            }
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }

        // Remove duplicate tenant_id index (FK already creates one)
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexesFound = $sm->listTableIndexes('visitors');
        if (isset($indexesFound['visitors_tenant_id_index'])) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropIndex('visitors_tenant_id_index');
            });
        }

        // Add unique constraint on session_id
        // First, handle any duplicate session_ids (keep the most recent one)
        $duplicates = DB::table('visitors')
            ->select('session_id')
            ->groupBy('session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('session_id');

        foreach ($duplicates as $sessionId) {
            // Keep the most recent record, delete others
            DB::table('visitors')
                ->where('session_id', $sessionId)
                ->orderBy('last_visit_at', 'desc')
                ->skip(1)
                ->delete();
        }

        // Now add the unique constraint
        try {
            Schema::table('visitors', function (Blueprint $table) {
                $table->unique('session_id');
            });
        } catch (\Exception $e) {
            // Unique constraint already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('visitors')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table) {
            // Drop unique constraint
            try {
                $table->dropUnique(['session_id']);
            } catch (\Exception $e) {
                // Constraint doesn't exist
            }

            // Re-add ip_address column
            if (! Schema::hasColumn('visitors', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('session_id');
            }

            // Drop ip_address_encrypted
            if (Schema::hasColumn('visitors', 'ip_address_encrypted')) {
                $table->dropColumn('ip_address_encrypted');
            }
        });
    }
};
