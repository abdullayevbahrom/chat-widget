<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drop tables that are no longer used:
     * - tenant_domains: Replaced by project.domain field
     * - project_domains: Replaced by project.domain field
     * - csp_reports: Not needed for MVP
     */
    public function up(): void
    {
        Schema::dropIfExists('csp_reports');
        Schema::dropIfExists('project_domains');
        Schema::dropIfExists('tenant_domains');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate tenant_domains
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('domain');
            $table->boolean('is_active')->default(true);
            $table->string('verification_status')->default('pending');
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'domain']);
        });

        // Recreate project_domains
        Schema::create('project_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('domain');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['project_id', 'domain']);
        });

        // Recreate csp_reports
        Schema::create('csp_reports', function (Blueprint $table) {
            $table->id();
            $table->text('document_uri')->nullable();
            $table->text('referrer')->nullable();
            $table->text('blocked_uri')->nullable();
            $table->text('violated_directive')->nullable();
            $table->text('original_policy')->nullable();
            $table->text('disposition')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('sample')->nullable();
            $table->json('source_file')->nullable();
            $table->json('effective_directive')->nullable();
            $table->json('status_code')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
        });
    }
};
