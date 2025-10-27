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
        Schema::create('rendering_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_data_id')->nullable()->constrained('template_data')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->integer('progress')->default(0); // 0-100
            $table->string('pdf_url')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Store spec, template info, etc.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('user_id');
            $table->index('template_data_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rendering_jobs');
    }
};
