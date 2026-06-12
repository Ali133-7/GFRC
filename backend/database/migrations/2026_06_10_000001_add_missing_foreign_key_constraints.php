<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add missing foreign key constraints to improve data integrity.
     * Note: SQLite does not support adding foreign keys to existing tables.
     * This migration will be skipped on SQLite.
     */
    public function up(): void
    {
        // Skip on SQLite - foreign keys must be defined during table creation
        if (DB::getDriverName() === 'sqlite') {
            echo "SQLite detected - skipping foreign key constraints (not supported on existing tables)\n";
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            // Add FK for workflow_version_id if not exists
            $table->foreign('workflow_version_id', 'activity_log_workflow_version_id_foreign')
                ->references('id')->on('workflow_versions')
                ->onDelete('set null');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            // Add FK for execution_id
            $table->foreign('execution_id', 'activity_log_execution_id_foreign')
                ->references('id')->on('workflow_executions')
                ->onDelete('set null');
        });

        Schema::table('workflow_routing_log', function (Blueprint $table) {
            // Add FK for from_step_id
            $table->foreign('from_step_id', 'workflow_routing_log_from_step_id_foreign')
                ->references('id')->on('workflow_steps')
                ->nullOnDelete();
            
            // Add FK for trigger_rule_id
            $table->foreign('trigger_rule_id', 'workflow_routing_log_trigger_rule_id_foreign')
                ->references('id')->on('workflow_rules')
                ->nullOnDelete();
        });

        Schema::table('field_state_history', function (Blueprint $table) {
            // Add FK for field_id
            $table->foreign('field_id', 'field_state_history_field_id_foreign')
                ->references('id')->on('workflow_fields')
                ->nullOnDelete();
            
            // Add FK for rule_id
            $table->foreign('rule_id', 'field_state_history_rule_id_foreign')
                ->references('id')->on('workflow_rules')
                ->nullOnDelete();
        });

        Schema::table('workflow_fields', function (Blueprint $table) {
            // Add self-referential FK for parent_field_id
            $table->foreign('parent_field_id', 'workflow_fields_parent_field_id_foreign')
                ->references('id')->on('workflow_fields')
                ->nullOnDelete();
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            // Add FK for trigger_field_id
            $table->foreign('trigger_field_id', 'workflow_rules_trigger_field_id_foreign')
                ->references('id')->on('workflow_fields')
                ->nullOnDelete();
        });

        Schema::table('validation_rules', function (Blueprint $table) {
            // Add FK for trigger_field_id
            $table->foreign('trigger_field_id', 'validation_rules_trigger_field_id_foreign')
                ->references('id')->on('workflow_fields')
                ->nullOnDelete();
        });

        Schema::table('template_rules', function (Blueprint $table) {
            // Add FK for trigger_field_id with nullOnDelete
            $table->foreign('trigger_field_id', 'template_rules_trigger_field_id_foreign')
                ->references('id')->on('register_fields')
                ->nullOnDelete();
            
            // Add FK for target_field_id with nullOnDelete
            $table->foreign('target_field_id', 'template_rules_target_field_id_foreign')
                ->references('id')->on('register_fields')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip on SQLite
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign('activity_log_workflow_version_id_foreign');
            $table->dropForeign('activity_log_execution_id_foreign');
        });

        Schema::table('workflow_routing_log', function (Blueprint $table) {
            $table->dropForeign('workflow_routing_log_from_step_id_foreign');
            $table->dropForeign('workflow_routing_log_trigger_rule_id_foreign');
        });

        Schema::table('field_state_history', function (Blueprint $table) {
            $table->dropForeign('field_state_history_field_id_foreign');
            $table->dropForeign('field_state_history_rule_id_foreign');
        });

        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropForeign('workflow_fields_parent_field_id_foreign');
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->dropForeign('workflow_rules_trigger_field_id_foreign');
        });

        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropForeign('validation_rules_trigger_field_id_foreign');
        });

        Schema::table('template_rules', function (Blueprint $table) {
            $table->dropForeign('template_rules_trigger_field_id_foreign');
            $table->dropForeign('template_rules_target_field_id_foreign');
        });
    }
};
