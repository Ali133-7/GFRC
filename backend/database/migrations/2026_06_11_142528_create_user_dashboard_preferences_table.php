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
        Schema::create('user_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Homepage settings
            $table->foreignId('default_dashboard_id')->nullable()->constrained('dashboards')->nullOnDelete();
            $table->string('default_view')->default('dashboard'); // dashboard, my-workspace, reports, etc.
            
            // Theme preferences
            $table->string('theme')->default('light'); // light, dark, auto
            $table->string('color_palette')->nullable();
            $table->string('font_size')->default('medium'); // small, medium, large
            $table->string('layout_density')->default('comfortable'); // compact, comfortable, spacious
            
            // Widget preferences
            $table->boolean('auto_refresh_widgets')->default(true);
            $table->integer('default_refresh_interval')->default(60); // seconds
            $table->boolean('show_widget_borders')->default(true);
            $table->boolean('show_widget_shadows')->default(true);
            
            // Display preferences
            $table->boolean('show_notifications')->default(true);
            $table->boolean('show_announcements')->default(true);
            $table->boolean('show_quick_actions')->default(true);
            $table->boolean('show_favorites')->default(true);
            
            // Workspace preferences
            $table->json('quick_links')->nullable();
            $table->json('favorite_reports')->nullable();
            $table->json('favorite_workflows')->nullable();
            $table->json('favorite_registers')->nullable();
            $table->json('bookmarks')->nullable();
            
            // Executive mode
            $table->boolean('executive_mode')->default(false);
            $table->boolean('tv_mode')->default(false);
            $table->integer('tv_rotation_interval')->default(30); // seconds
            
            $table->timestamps();
            
            $table->unique(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
    }
};
