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
        Schema::create('dashboard_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            
            // Layout
            $table->integer('sort_order')->default(0);
            $table->string('layout_type')->default('grid'); // grid, list, tabs, carousel
            $table->json('layout_config')->nullable(); // Columns, rows, spacing
            
            // Styling
            $table->string('background_color')->nullable();
            $table->string('border_color')->nullable();
            $table->integer('padding')->default(16);
            
            // Visibility
            $table->boolean('is_collapsible')->default(false);
            $table->boolean('is_collapsed')->default(false);
            $table->boolean('is_visible')->default(true);
            
            // Conditional display
            $table->json('display_conditions')->nullable(); // Show/hide based on conditions
            $table->json('permissions')->nullable(); // Required permissions to view
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['dashboard_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_sections');
    }
};
