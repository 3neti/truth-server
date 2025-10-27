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
        // Check if we need to migrate - only if table exists with old schema
        if (!Schema::hasColumn('template_data', 'document_id')) {
            // First, check if there's existing data and generate document_ids
            $existingData = \DB::table('template_data')->get();
            
            Schema::table('template_data', function (Blueprint $table) {
                // Drop indexes first if they exist
                if (Schema::hasColumn('template_data', 'category')) {
                    try {
                        $table->dropIndex(['category']);
                    } catch (\Exception $e) {
                        // Index might not exist
                    }
                }
                
                try {
                    $table->dropIndex(['template_ref']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                
                // Add new columns (nullable first to avoid errors)
                $table->string('document_id')->nullable()->after('id');
                $table->foreignId('template_id')->nullable()->after('template_ref')->constrained('templates')->onDelete('set null');
                $table->boolean('portable_format')->default(false)->after('template_id');
                
                // Only add compiled_spec if data column exists
                if (Schema::hasColumn('template_data', 'data')) {
                    $table->json('compiled_spec')->nullable()->after('data');
                }
                
                // Modify existing columns
                $table->string('name')->nullable()->change();
                
                // Drop old columns if they exist
                if (Schema::hasColumn('template_data', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('template_data', 'is_public')) {
                    $table->dropColumn('is_public');
                }
                if (Schema::hasColumn('template_data', 'category')) {
                    $table->dropColumn('category');
                }
            });
            
            // Populate document_id for existing records
            foreach ($existingData as $record) {
                \DB::table('template_data')
                    ->where('id', $record->id)
                    ->update(['document_id' => 'DOC-' . str_pad($record->id, 5, '0', STR_PAD_LEFT)]);
            }
            
            // Rename data to json_data if data column exists
            if (Schema::hasColumn('template_data', 'data')) {
                Schema::table('template_data', function (Blueprint $table) {
                    $table->renameColumn('data', 'json_data');
                });
            }
            
            // Now make document_id unique and not nullable
            Schema::table('template_data', function (Blueprint $table) {
                $table->string('document_id')->nullable(false)->change();
                $table->unique('document_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_data', function (Blueprint $table) {
            // Restore old columns
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('category')->default('general');
            
            // Rename json_data back to data
            $table->renameColumn('json_data', 'data');
            
            // Drop new columns
            $table->dropColumn(['document_id', 'template_id', 'portable_format', 'compiled_spec']);
            
            // Revert name to required
            $table->string('name')->nullable(false)->change();
        });
    }
};
