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
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('description')->nullable();
            
            // Dashboard ownership hierarchy
            $table->enum('scope', ['system', 'organization', 'department', 'role', 'user'])->default('system');
            
            // Foreign keys for hierarchy (nullable, no constraints for flexibility)
            $table->uuid('organization_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Template inheritance
            $table->foreignId('template_id')->nullable()->constrained('dashboard_templates')->nullOnDelete();
            $table->foreignId('parent_dashboard_id')->nullable()->constrained('dashboards')->nullOnDelete();
            
            // Layout and configuration
            $table->json('layout_config')->nullable(); // Grid layout, sections arrangement
            $table->json('theme_config')->nullable(); // Colors, fonts, styling
            $table->json('settings')->nullable(); // Additional settings
            
            // Visibility and access
            $table->enum('visibility', ['private', 'shared', 'department', 'role', 'organization', 'public'])->default('private');
            $table->boolean('is_default')->default(false); // Default dashboard for this scope
            $table->boolean('is_active')->default(true);
            
            // Versioning
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('published');
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            // Indexes for hierarchy resolution
            $table->index(['scope', 'is_active']);
            $table->index(['user_id', 'is_default']);
            $table->index(['role_id', 'is_default']);
            $table->index(['department_id', 'is_default']);
            $table->index(['visibility', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
