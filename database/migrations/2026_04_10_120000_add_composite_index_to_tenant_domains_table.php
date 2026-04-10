<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a composite index on (domain, is_active) to tenant_domains table
     * for faster domain lookups in the TenantResolver and CheckTenantDomainWhitelist middleware.
     */
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            // The index already exists from the original migration, but we ensure
            // the composite (domain, is_active) index is explicitly defined for
            // query optimization on domain + is_active WHERE clauses.
            if (! $this->hasIndex('tenant_domains', 'tenant_domains_domain_is_active_index')) {
                $table->index(['domain', 'is_active'], 'tenant_domains_domain_is_active_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropIndex('tenant_domains_domain_is_active_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(fn ($index) => $index['name'] === $indexName);
    }
};
