<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('page_key', 100)->index(); // e.g., 'workflow_designer', 'registers', 'dashboard'
            $table->string('category', 50)->nullable(); // e.g., 'workflows', 'registers', 'settings'
            $table->string('title_ar', 300);
            $table->string('title_en', 300)->nullable();
            $table->text('content_ar');
            $table->text('content_en')->nullable();
            $table->jsonb('media')->nullable(); // [{ type: 'image'|'gif'|'video', url, caption }]
            $table->jsonb('links')->nullable(); // [{ label, url }]
            $table->jsonb('examples')->nullable(); // Dynamic examples structure
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System-generated vs admin-created
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
