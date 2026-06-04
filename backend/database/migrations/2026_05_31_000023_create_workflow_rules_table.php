<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('name', 200)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('condition_logic');
            $table->jsonb('actions');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_rules');
    }
};
