<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_version_id')->constrained('workflow_versions');
            $table->foreignUuid('register_id')->constrained('registers');
            $table->string('status', 20)->default('in_progress'); // in_progress, completed, cancelled, abandoned
            $table->integer('current_step_index')->default(0);
            $table->jsonb('values_snapshot')->default('{}');
            $table->jsonb('calculated_items')->default('[]');
            $table->decimal('total_amount', 15, 3)->default(0);
            $table->foreignUuid('receipt_id')->nullable()->constrained('receipts');
            $table->foreignUuid('started_by')->constrained('users');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
