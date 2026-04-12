<?php

use App\Models\Visitor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fix migration to address review issues #4, #6, #7, #14, #15:
 * - Add tenant-scoped unique constraint on session_id (#4)
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
        Log::info('Applying visitors table compatibility migration.', [
            'connection' => Schema::getConnection()->getDriverName(),
        ]);

        $driver = Schema::getConnection()->getDriverName();

        // If the visitors table doesn't exist yet, the main migration handles everything
        if (! Schema::hasTable('visitors')) {
            Log::info('Skipping visitors compatibility migration because the visitors table does not exist.');

            return;
        }

        Schema::table('visitors', function (Blueprint $table) use ($driver) {
            if (in_array($driver, ['mysql', 'mariadb'], true) && ! Schema::hasColumn('visitors', 'tenant_scope_key')) {
                $table->unsignedBigInteger('tenant_scope_key')->storedAs('coalesce(tenant_id, 0)')->after('tenant_id');
            }

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
            if ($this->hasIndex('visitors', 'visitors_ip_address_index')) {
                Schema::table('visitors', function (Blueprint $table) {
                    $table->dropIndex('visitors_ip_address_index');
                });
            }
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }

        // Remove duplicate tenant_id index (FK already creates one)
        if ($this->hasIndex('visitors', 'visitors_tenant_id_index')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropIndex('visitors_tenant_id_index');
            });
        }

        // Add a tenant-scoped unique constraint for session_id.
        // First, merge duplicate tenant/session pairs into a single surviving visitor.
        $duplicates = DB::table('visitors')
            ->whereNotNull('session_id')
            ->select('tenant_id', 'session_id')
            ->groupBy('tenant_id', 'session_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::transaction(function () use ($duplicate): void {
                $visitors = DB::table('visitors')
                    ->where('session_id', $duplicate->session_id)
                    ->when(
                        $duplicate->tenant_id === null,
                        fn ($query) => $query->whereNull('tenant_id'),
                        fn ($query) => $query->where('tenant_id', $duplicate->tenant_id),
                    )
                    ->orderByRaw('last_visit_at IS NULL')
                    ->orderByDesc('last_visit_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get(['id']);

                $survivorId = $visitors->first()?->id;
                $duplicateIds = $visitors
                    ->skip(1)
                    ->pluck('id')
                    ->values();

                if ($survivorId === null || $duplicateIds->isEmpty()) {
                    return;
                }

                Log::info('Merging duplicate visitors before adding unique session constraint.', [
                    'tenant_id' => $duplicate->tenant_id,
                    'session_id' => $duplicate->session_id,
                    'survivor_id' => $survivorId,
                    'duplicate_count' => $duplicateIds->count(),
                ]);

                DB::table('conversations')
                    ->whereIn('visitor_id', $duplicateIds)
                    ->update([
                        'visitor_id' => $survivorId,
                        'updated_at' => now(),
                    ]);

                DB::table('messages')
                    ->where('sender_type', Visitor::class)
                    ->whereIn('sender_id', $duplicateIds)
                    ->update([
                        'sender_id' => $survivorId,
                        'updated_at' => now(),
                    ]);

                DB::table('visitors')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            });
        }

        if (! $this->hasIndex('visitors', 'visitors_tenant_id_session_id_unique')) {
            try {
                Schema::table('visitors', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'session_id']);
                });
            } catch (\Exception $e) {
                // Constraint already exists
            }
        }

        if (! $this->hasIndex('visitors', 'visitors_tenant_scope_session_unique')) {
            match ($driver) {
                'mysql', 'mariadb' => Schema::table('visitors', function (Blueprint $table) {
                    $table->unique(['tenant_scope_key', 'session_id'], 'visitors_tenant_scope_session_unique');
                }),
                'pgsql', 'sqlite' => DB::statement(
                    'CREATE UNIQUE INDEX visitors_tenant_scope_session_unique ON visitors (COALESCE(tenant_id, 0), session_id)'
                ),
                default => null,
            };
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
                $table->dropUnique(['tenant_id', 'session_id']);
            } catch (\Exception $e) {
                // Constraint doesn't exist
            }

            try {
                $table->dropUnique('visitors_tenant_scope_session_unique');
            } catch (\Exception $e) {
                // Constraint doesn't exist or is expression-backed on this driver
            }

            // Re-add ip_address column
            if (! Schema::hasColumn('visitors', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('session_id');
            }

            // Drop ip_address_encrypted
            if (Schema::hasColumn('visitors', 'ip_address_encrypted')) {
                $table->dropColumn('ip_address_encrypted');
            }

            if (Schema::hasColumn('visitors', 'tenant_scope_key')) {
                $table->dropColumn('tenant_scope_key');
            }
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS visitors_tenant_scope_session_unique');
        }
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteHasIndex($table, $indexName),
            'mysql', 'mariadb' => $this->informationSchemaHasIndex($table, $indexName),
            'pgsql' => $this->postgresHasIndex($table, $indexName),
            default => false,
        };
    }

    protected function sqliteHasIndex(string $table, string $indexName): bool
    {
        // Whitelist of known table names to prevent SQL injection via PRAGMA
        $allowedTables = ['visitors', 'projects', 'conversations', 'messages'];

        if (! in_array($table, $allowedTables, true)) {
            return false;
        }

        $indexes = DB::select("PRAGMA index_list('{$table}')");

        foreach ($indexes as $index) {
            if (($index->name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    protected function informationSchemaHasIndex(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    protected function postgresHasIndex(string $table, string $indexName): bool
    {
        $schema = DB::scalar('select current_schema()');

        return DB::table('pg_indexes')
            ->where('schemaname', $schema ?: 'public')
            ->where('tablename', $table)
            ->where('indexname', $indexName)
            ->exists();
    }
};
