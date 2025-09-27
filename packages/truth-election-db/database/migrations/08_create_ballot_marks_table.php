<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ballot_marks', function (Blueprint $table) {
            $table->id();
            $table->string('ballot_code')->index();
            $table->string('mark_key');
            $table->timestamps();

            $table->unique(['ballot_code', 'mark_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_marks');
    }
};
