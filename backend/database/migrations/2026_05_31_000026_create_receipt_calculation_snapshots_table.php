<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_calculation_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions');
            $table->jsonb('workflow_definition');
            $table->jsonb('rules_applied');
            $table->jsonb('fees_used');
            $table->jsonb('field_values');
            $table->string('calculation_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_calculation_snapshots');
    }
};
