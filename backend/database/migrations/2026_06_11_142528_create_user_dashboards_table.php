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
        Schema::create('user_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            
            // User-specific settings
            $table->string('custom_name')->nullable(); // User's custom name for the dashboard
            $table->boolean('is_favorite')->default(false);
            $table->integer('sort_order')->default(0); // Order in user's dashboard list
            $table->boolean('is_pinned')->default(false);
            
            // Layout overrides
            $table->json('layout_overrides')->nullable(); // User's custom layout
            $table->json('widget_positions')->nullable(); // User's widget positions
            $table->json('widget_sizes')->nullable(); // User's widget sizes
            
            // Visibility
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_hidden_by_user')->default(false);
            
            // Inheritance tracking
            $table->boolean('inherits_from_role')->default(true);
            $table->boolean('inherits_from_department')->default(true);
            $table->boolean('allow_inheritance_updates')->default(true);
            
            $table->timestamps();
            
            $table->unique(['user_id', 'dashboard_id']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_dashboards');
    }
};
