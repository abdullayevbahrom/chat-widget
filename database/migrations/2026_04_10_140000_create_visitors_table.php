<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('visitors', function (Blueprint $table) use ($driver) {
            $table->id();
            // Foreign key automatically creates an index — no separate index needed (Issue #7)
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('set null');
            // Unique constraint ensures one visitor record per tenant/session pair.
            $table->string('session_id');
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->unsignedBigInteger('tenant_scope_key')->storedAs('coalesce(tenant_id, 0)');
            }
            // IP address will be encrypted for GDPR compliance (Issue #14)
            $table->text('ip_address_encrypted')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->text('current_url')->nullable();
            $table->string('current_page', 500)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('platform_version', 50)->nullable();
            $table->string('language', 20)->nullable();
            // country/city fields kept nullable for future geo-IP integration (Issue #6)
            $table->string('country', 100)->nullable()->comment('Geo-IP country, populated when geo-IP service is added');
            $table->string('city', 100)->nullable()->comment('Geo-IP city, populated when geo-IP service is added');
            $table->boolean('is_authenticated')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('first_visit_at');
            $table->timestamp('last_visit_at');
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamps();

            // No duplicate tenant_id index — foreign key already creates one (Issue #7)
            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'session_id']);
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->unique(['tenant_scope_key', 'session_id'], 'visitors_tenant_scope_session_unique');
            }
            $table->index('last_visit_at');
        });

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX visitors_tenant_scope_session_unique ON visitors (COALESCE(tenant_id, 0), session_id)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
