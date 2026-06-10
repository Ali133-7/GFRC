<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_state_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->uuid('field_id')->nullable();
            $table->uuid('rule_id')->nullable();
            $table->jsonb('old_state')->nullable();
            $table->jsonb('new_state')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_state_history');
    }
};
