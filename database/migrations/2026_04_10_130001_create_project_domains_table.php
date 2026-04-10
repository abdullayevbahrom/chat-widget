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
        Schema::create('project_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('domain');
            $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->string('verification_token')->nullable(); // DNS/HTTP verification uchun
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'domain']);
            $table->index(['domain', 'verification_status']);
            $table->index(['project_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_domains');
    }
};
