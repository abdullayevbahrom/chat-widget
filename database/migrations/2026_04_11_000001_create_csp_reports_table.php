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
        Schema::create('csp_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('violated_directive', 255);
            $table->string('blocked_uri', 500);
            $table->string('document_uri', 500)->nullable();
            $table->string('source_file', 500)->nullable();
            $table->string('disposition', 50)->default('enforce');
            $table->string('effective_directive', 255)->nullable();
            $table->integer('line_number')->nullable();
            $table->integer('column_number')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('original_policy')->nullable();
            $table->text('script_sample')->nullable();
            $table->integer('count')->default(1);
            $table->timestamps();

            $table->index(['violated_directive', 'blocked_uri', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csp_reports');
    }
};
