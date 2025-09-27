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
        Schema::create('precincts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // e.g., CURRIMAO-001
            $table->string('location_name'); // e.g., Currimao Central School, TODO: change to polling_place
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->json('electoral_inspectors')->nullable();
            $table->schemalessAttributes('meta');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precincts');
    }
};
