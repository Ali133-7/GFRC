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
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('dashboard_sections')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('widget_type'); // kpi_card, chart, table, list, calendar, notes, shortcuts, etc.
            $table->string('data_source')->nullable(); // API endpoint, query, model
            
            // Position and size
            $table->integer('sort_order')->default(0);
            $table->integer('grid_x')->default(0); // Grid position X
            $table->integer('grid_y')->default(0); // Grid position Y
            $table->integer('grid_width')->default(6); // Width in grid units (1-12)
            $table->integer('grid_height')->default(4); // Height in grid units
            
            // Data configuration
            $table->json('data_config')->nullable(); // Query parameters, filters, aggregations
            $table->json('display_config')->nullable(); // Colors, formats, thresholds
            $table->json('filter_config')->nullable(); // User/role/department filters
            
            // Dynamic filtering
            $table->boolean('filter_by_user')->default(false);
            $table->boolean('filter_by_department')->default(false);
            $table->boolean('filter_by_role')->default(false);
            $table->boolean('filter_by_branch')->default(false);
            $table->json('custom_filters')->nullable();
            
            // Refresh settings
            $table->integer('refresh_interval')->default(0); // Seconds, 0 = no auto-refresh
            $table->boolean('is_real_time')->default(false);
            
            // Visibility
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_removable')->default(true);
            $table->json('visibility_rules')->nullable();
            
            // Permissions
            $table->json('required_permissions')->nullable();
            $table->json('allowed_roles')->nullable();
            $table->json('allowed_departments')->nullable();
            
            // Inheritance
            $table->foreignId('template_widget_id')->nullable()->constrained('dashboard_widgets')->nullOnDelete();
            $table->boolean('is_inherited')->default(false);
            $table->boolean('is_customized')->default(false);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['section_id', 'sort_order']);
            $table->index(['widget_type', 'is_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
