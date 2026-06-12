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
        Schema::create('dashboard_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            
            // Permission target (who can access)
            $table->enum('permission_type', ['user', 'role', 'department', 'organization']);
            $table->string('permission_target_id'); // ID of user/role/department/organization
            
            // Access levels
            $table->boolean('can_view')->default(true);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_customize')->default(false);
            $table->boolean('can_share')->default(false);
            $table->boolean('can_delete')->default(false);
            
            // Widget-level permissions
            $table->json('widget_permissions')->nullable(); // Specific widget access
            
            // Time-based access
            $table->time('available_from')->nullable();
            $table->time('available_to')->nullable();
            $table->json('available_days')->nullable(); // Days of week
            
            // Conditional access
            $table->json('conditions')->nullable(); // Additional conditions for access
            
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['dashboard_id', 'permission_type']);
            $table->index(['permission_type', 'permission_target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_permissions');
    }
};
