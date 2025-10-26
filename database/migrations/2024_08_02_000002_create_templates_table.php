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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // election, survey, test, etc.
            
            // Template content
            $table->longText('handlebars_template')->nullable(); // Nullable for remote templates
            $table->json('sample_data')->nullable();
            
            // Schema and validation
            $table->json('schema')->nullable();
            $table->json('json_schema')->nullable();
            
            // Ownership and visibility
            $table->boolean('is_public')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Family relationship
            $table->foreignId('family_id')->nullable()->constrained('template_families')->onDelete('set null');
            $table->string('layout_variant')->default('default');
            $table->string('version')->default('1.0.0');
            
            // Storage (local vs remote)
            $table->enum('storage_type', ['local', 'remote'])->default('local');
            $table->string('template_uri')->nullable(); // Full URI reference for remote templates
            $table->json('remote_metadata')->nullable(); // Cache metadata
            $table->text('cached_template')->nullable(); // Cached content from remote
            $table->timestamp('last_fetched_at')->nullable(); // Cache timestamp
            
            // Signing and verification
            $table->string('checksum_sha256', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            
            $table->timestamps();
            
            $table->index(['category', 'is_public']);
            $table->index('user_id');
            $table->index(['family_id', 'layout_variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
