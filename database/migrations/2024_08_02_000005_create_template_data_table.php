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
        Schema::create('template_data', function (Blueprint $table) {
            $table->id();
            $table->string('document_id')->unique();
            $table->string('name')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->onDelete('set null');
            $table->string('template_ref')->nullable(); // URI to template (local:family/variant, github:org/repo, etc.)
            $table->boolean('portable_format')->default(false);
            $table->json('json_data'); // The actual data object
            $table->json('compiled_spec')->nullable(); // Compiled template spec
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('document_id');
            $table->index('template_id');
            $table->index('template_ref');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_data');
    }
};
