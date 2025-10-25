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
        Schema::table('templates', function (Blueprint $table) {
            $table->json('json_schema')->nullable()->after('schema');
            $table->string('checksum_sha256', 64)->nullable()->after('version');
            $table->timestamp('verified_at')->nullable()->after('checksum_sha256');
            $table->foreignId('verified_by')->nullable()->constrained('users')->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['json_schema', 'checksum_sha256', 'verified_at', 'verified_by']);
        });
    }
};
