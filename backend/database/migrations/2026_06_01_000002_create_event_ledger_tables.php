<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Workflow Execution Events (append-only ledger)
        Schema::create('workflow_execution_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained('workflow_executions')->onDelete('cascade');
            $table->string('event_type', 50); // execution_started, step_submitted, step_failed, execution_completed, execution_cancelled, execution_replayed
            $table->integer('sequence')->unsigned(); // strict ordering per execution
            $table->jsonb('event_payload')->default('{}'); // raw event data
            $table->jsonb('calculated_items')->default('[]'); // fee calculations at this point
            $table->jsonb('fee_snapshot')->default('{}'); // fee_code => {fee_version_id, amount, fee_name}
            $table->jsonb('context_snapshot')->default('{}'); // CalculationContext state at event time
            $table->string('previous_event_hash', 64)->nullable(); // chain integrity
            $table->string('hash', 64); // SHA-256 of this event
            $table->string('idempotency_key', 100)->nullable();
            $table->string('caused_by', 50)->nullable(); // user_id, system, replay
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['execution_id', 'sequence']);
            $table->index(['execution_id', 'created_at']);
            $table->index('event_type');
            $table->index('idempotency_key');
        });

        // Receipt Events (append-only ledger)
        Schema::create('receipt_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts')->onDelete('cascade');
            $table->string('event_type', 50); // receipt_created, receipt_issued, receipt_revised, receipt_cancelled, receipt_printed
            $table->integer('sequence')->unsigned(); // strict ordering per receipt
            $table->jsonb('before_state')->nullable(); // state before this event
            $table->jsonb('after_state'); // state after this event
            $table->jsonb('fee_snapshot')->default('{}'); // fee versions used
            $table->jsonb('context_snapshot')->default('{}'); // calculation context
            $table->integer('lock_version')->unsigned(); // version after this event
            $table->string('previous_event_hash', 64)->nullable(); // chain integrity
            $table->string('hash', 64); // SHA-256 of this event
            $table->string('idempotency_key', 100)->nullable();
            $table->string('caused_by', 50)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('reason')->nullable(); // for revisions/cancellations
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['receipt_id', 'sequence']);
            $table->index(['receipt_id', 'created_at']);
            $table->index('event_type');
            $table->index('idempotency_key');
        });

        // Persistent Idempotency Keys
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('entity_type', 50); // receipt, workflow_execution
            $table->foreignUuid('entity_id')->nullable(); // links to the created entity
            $table->string('request_hash', 64); // SHA-256 of request payload
            $table->jsonb('response_snapshot')->nullable(); // cached response for replay
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null = never expires (financial)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('receipt_events');
        Schema::dropIfExists('workflow_execution_events');
    }
};
