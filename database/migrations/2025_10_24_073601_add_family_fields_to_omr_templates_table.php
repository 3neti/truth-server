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
        Schema::table('omr_templates', function (Blueprint $table) {
            $table->foreignId('family_id')->nullable()->after('user_id')->constrained('template_families')->onDelete('set null');
            $table->string('layout_variant')->default('default')->after('family_id');
            $table->string('version')->default('1.0.0')->after('layout_variant');
            
            $table->index(['family_id', 'layout_variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('omr_templates', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'layout_variant']);
            $table->dropForeign(['family_id']);
            $table->dropColumn(['family_id', 'layout_variant', 'version']);
        });
    }
};
