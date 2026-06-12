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
        Schema::create('dashboard_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('description')->nullable();
            $table->string('category')->nullable(); // 'financial', 'operations', 'audit', 'executive', etc.
            $table->string('role_type')->nullable(); // 'cashier', 'auditor', 'manager', 'director', 'minister'
            $table->json('layout_config')->nullable(); // Default layout configuration
            $table->json('default_widgets')->nullable(); // Default widgets for this template
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System-provided template
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index(['role_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_templates');
    }
};
