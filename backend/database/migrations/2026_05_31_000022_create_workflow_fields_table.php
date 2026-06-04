<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->foreignUuid('register_field_id')->constrained('register_fields')->cascadeOnDelete();
            $table->foreignUuid('step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->string('label_override', 200)->nullable();
            $table->string('placeholder', 200)->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_financial')->default(false);
            $table->integer('sort_order')->default(0);
            $table->jsonb('condition_logic')->nullable();
            $table->string('fee_code', 50)->nullable();
            $table->text('calculation_formula')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_fields');
    }
};
