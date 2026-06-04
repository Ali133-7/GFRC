<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();

            // Rule identification
            $table->string('name', 200)->nullable();
            $table->text('description')->nullable();

            // Validation type: duplicate_check, exists, multi_field, register_search, query_builder, sql
            $table->string('validation_type', 50);

            // Target configuration
            $table->foreignUuid('target_register_id')->nullable()->constrained('registers')->nullOnDelete();
            $table->jsonb('target_fields')->nullable(); // [{ workflow_field_id, register_field_name }]

            // Query configuration
            $table->jsonb('query_conditions')->nullable(); // For query builder
            $table->text('sql_query')->nullable(); // For SQL validation
            $table->string('sql_condition', 100)->nullable(); // e.g., "count = 0"

            // Response configuration
            $table->string('response_type', 20)->default('error'); // error, warning, confirm
            $table->string('error_message_ar', 500)->nullable();
            $table->string('error_message_en', 500)->nullable();
            $table->string('confirm_message_ar', 500)->nullable();
            $table->string('confirm_message_en', 500)->nullable();

            // Ordering and activation
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_rules');
    }
};
