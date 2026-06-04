<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            // Execution mode: create, update, renewal, review
            $table->string('mode', 30)->default('create')->after('status');

            // Branch state: tracks active branch, redirects, paused state
            $table->jsonb('branch_state')->nullable()->after('mode');
            // Structure:
            // {
            //   "active_branch": "default|update|renewal|custom",
            //   "redirect_to_workflow_id": null|"uuid",
            //   "redirect_to_step_id": null|"uuid",
            //   "paused": false,
            //   "pause_reason": null,
            //   "original_execution_id": null
            // }

            // Routing history: log all transitions
            $table->jsonb('routing_history')->nullable()->after('branch_state');
            // Structure:
            // [
            //   {
            //     "event": "workflow_redirect|mode_switch|step_skip|execution_pause",
            //     "from_workflow_id": "uuid",
            //     "to_workflow_id": "uuid|null",
            //     "from_mode": "create",
            //     "to_mode": "update",
            //     "trigger_field": "file_number",
            //     "trigger_value": "12345",
            //     "rule_id": "uuid",
            //     "rule_name": "...",
            //     "timestamp": "2026-06-03T..."
            //   }
            // ]

            // Preserved state: values carried over from redirected execution
            $table->jsonb('preserved_values')->nullable()->after('routing_history');

            // State mapping: how fields map between workflows during redirect
            $table->jsonb('state_mapping')->nullable()->after('preserved_values');

            // Index for mode-based queries
            $table->index(['mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex(['mode', 'status']);
            $table->dropColumn([
                'mode',
                'branch_state',
                'routing_history',
                'preserved_values',
                'state_mapping',
            ]);
        });
    }
};
