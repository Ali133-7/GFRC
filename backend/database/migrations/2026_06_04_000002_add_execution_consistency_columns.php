<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->jsonb('field_states')->default('{}')->after('values_snapshot');
            $table->jsonb('rule_results')->default('[]')->after('field_states');
            $table->jsonb('validation_results')->default('[]')->after('rule_results');
            $table->jsonb('routing_decisions')->default('[]')->after('validation_results');
            $table->jsonb('financial_trace')->default('[]')->after('routing_decisions');
            $table->timestamp('last_saved_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropColumn([
                'field_states',
                'rule_results',
                'validation_results',
                'routing_decisions',
                'financial_trace',
                'last_saved_at',
            ]);
        });
    }
};
