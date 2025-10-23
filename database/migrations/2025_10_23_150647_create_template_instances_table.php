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
        Schema::create('template_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('omr_templates')->onDelete('cascade');
            $table->string('document_id')->unique();
            $table->json('data');
            $table->json('compiled_spec');
            $table->string('pdf_path')->nullable();
            $table->string('coords_path')->nullable();
            $table->timestamps();

            $table->index('template_id');
            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_instances');
    }
};
