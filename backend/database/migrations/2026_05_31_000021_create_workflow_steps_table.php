<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('title_ar', 200);
            $table->string('title_en', 200)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('condition_logic')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
