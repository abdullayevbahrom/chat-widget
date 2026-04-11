<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add tenant_id to personal_access_tokens for multi-tenant Sanctum isolation.
 *
 * This allows the ValidateSanctumTenantScope middleware to verify that
 * a token was created for the current tenant context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('tokenable_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
