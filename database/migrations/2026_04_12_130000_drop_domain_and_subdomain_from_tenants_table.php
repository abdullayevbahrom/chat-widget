<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'domain')) {
                $table->dropColumn('domain');
            }

            if (Schema::hasColumn('tenants', 'subdomain')) {
                $table->dropColumn('subdomain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'domain')) {
                $table->string('domain')->nullable()->unique();
            }

            if (! Schema::hasColumn('tenants', 'subdomain')) {
                $table->string('subdomain')->nullable()->unique();
            }
        });
    }
};
