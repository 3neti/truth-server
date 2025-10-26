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
        Schema::create('template_families', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // election, survey, test, etc.
            $table->string('repo_url')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_public')->default(true);
            $table->enum('storage_type', ['local', 'remote', 'hybrid'])->default('local');
            $table->string('repo_provider')->nullable(); // github, gitlab, http
            $table->string('repo_path')->nullable(); // path within repo
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            $table->index(['category', 'is_public']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_families');
    }
};
