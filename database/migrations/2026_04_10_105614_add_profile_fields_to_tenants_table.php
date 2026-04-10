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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('settings');
            $table->string('company_registration_number')->nullable()->after('company_name');
            $table->string('tax_id')->nullable()->after('company_registration_number');
            $table->text('company_address')->nullable()->after('tax_id');
            $table->string('company_city')->nullable()->after('company_address');
            $table->string('company_country', 2)->nullable()->after('company_city');
            $table->string('contact_phone')->nullable()->after('company_country');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('website')->nullable()->after('contact_email');
            $table->string('logo_path')->nullable()->after('website');
            $table->string('primary_contact_name')->nullable()->after('logo_path');
            $table->string('primary_contact_title')->nullable()->after('primary_contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_registration_number',
                'tax_id',
                'company_address',
                'company_city',
                'company_country',
                'contact_phone',
                'contact_email',
                'website',
                'logo_path',
                'primary_contact_name',
                'primary_contact_title',
            ]);
        });
    }
};
