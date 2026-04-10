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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            // Widget key: hashed (SHA-256) for security, like Laravel Sanctum tokens
            // The plaintext key is shown ONCE to the user at creation time
            $table->string('widget_key_hash', 64)->unique()->nullable();
            $table->string('widget_key_prefix', 8)->nullable(); // First 8 chars for UI display
            $table->text('description')->nullable(); // text, not string — description can be long
            $table->string('primary_domain')->nullable(); // Asosiy domain (ixtiyoriy)
            $table->json('settings')->nullable(); // Proekt darajasidagi sozlamalar
            $table->timestamp('widget_key_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
