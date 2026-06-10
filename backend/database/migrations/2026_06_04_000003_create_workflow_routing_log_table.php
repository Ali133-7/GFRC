<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_routing_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->foreignUuid('from_workflow_id')->constrained('workflows');
            $table->foreignUuid('to_workflow_id')->constrained('workflows');
            $table->uuid('from_step_id')->nullable();
            $table->uuid('trigger_rule_id')->nullable();
            $table->string('reason', 200)->nullable();
            $table->jsonb('values_snapshot')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_routing_log');
    }
};
