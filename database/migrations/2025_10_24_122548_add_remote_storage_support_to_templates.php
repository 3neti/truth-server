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
        // Template families
        Schema::table('template_families', function (Blueprint $table) {
            $table->enum('storage_type', ['local', 'remote', 'hybrid'])->default('local')->after('is_public');
            $table->string('repo_provider')->nullable()->after('repo_url'); // github, gitlab, http
            $table->string('repo_path')->nullable()->after('repo_provider'); // path within repo
        });

        // Templates
        Schema::table('templates', function (Blueprint $table) {
            $table->enum('storage_type', ['local', 'remote'])->default('local')->after('family_id');
            $table->string('template_uri')->nullable()->after('storage_type'); // Full URI reference
            $table->json('remote_metadata')->nullable()->after('template_uri'); // Cache metadata
            $table->text('cached_template')->nullable()->after('remote_metadata'); // Cached content
            $table->timestamp('last_fetched_at')->nullable()->after('cached_template'); // Cache timestamp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_families', function (Blueprint $table) {
            $table->dropColumn(['storage_type', 'repo_provider', 'repo_path']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['storage_type', 'template_uri', 'remote_metadata', 'cached_template', 'last_fetched_at']);
        });
    }
};
