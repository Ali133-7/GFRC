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
     * Add missing indexes to improve query performance.
     * Based on forensic audit findings.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // Skip on SQLite - indexes are created automatically or during table creation
        if ($driver === 'sqlite') {
            echo "SQLite detected - skipping some indexes (limited support on existing tables)\n";
        }

        try {
            // Receipts table indexes
            Schema::table('receipts', function (Blueprint $table) {
                $table->index('status', 'receipts_status_index');
                $table->index('created_by', 'receipts_created_by_index');
                $table->index('register_id', 'receipts_register_id_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Workflow executions table indexes
            Schema::table('workflow_executions', function (Blueprint $table) {
                $table->index('workflow_version_id', 'workflow_executions_workflow_version_id_index');
                $table->index('started_by', 'workflow_executions_started_by_index');
                $table->index('receipt_id', 'workflow_executions_receipt_id_index');
                $table->index('status', 'workflow_executions_status_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Official fees table indexes
            Schema::table('official_fees', function (Blueprint $table) {
                $table->index('is_active', 'official_fees_is_active_index');
                $table->index('effective_from', 'official_fees_effective_from_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Fee versions table indexes
            Schema::table('fee_versions', function (Blueprint $table) {
                $table->index(['fee_id', 'effective_from'], 'fee_versions_fee_id_effective_from_index');
                $table->index('effective_to', 'fee_versions_effective_to_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Validation rules table indexes
            Schema::table('validation_rules', function (Blueprint $table) {
                $table->index(['workflow_version_id', 'is_active'], 'validation_rules_workflow_version_id_is_active_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Records table indexes
            Schema::table('records', function (Blueprint $table) {
                $table->index('record_number', 'records_record_number_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Help articles table indexes
            Schema::table('help_articles', function (Blueprint $table) {
                $table->index('category', 'help_articles_category_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Template rules table indexes
            Schema::table('template_rules', function (Blueprint $table) {
                $table->index('trigger_field_id', 'template_rules_trigger_field_id_index');
                $table->index('target_field_id', 'template_rules_target_field_id_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Register fields table indexes
            Schema::table('register_fields', function (Blueprint $table) {
                $table->index('register_id', 'register_fields_register_id_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Workflow fields table indexes
            Schema::table('workflow_fields', function (Blueprint $table) {
                $table->index('workflow_version_id', 'workflow_fields_workflow_version_id_index');
                $table->index('step_id', 'workflow_fields_step_id_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Workflow rules table indexes
            Schema::table('workflow_rules', function (Blueprint $table) {
                $table->index(['workflow_version_id', 'is_active'], 'workflow_rules_workflow_version_id_is_active_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Workflow execution events table indexes
            Schema::table('workflow_execution_events', function (Blueprint $table) {
                $table->index('event_type', 'workflow_execution_events_event_type_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        try {
            // Receipt events table indexes
            Schema::table('receipt_events', function (Blueprint $table) {
                $table->index('event_type', 'receipt_events_event_type_index');
            });
        } catch (\Exception $e) { /* Ignore if exists */ }

        // PostgreSQL GIN index for JSONB
        if ($driver === 'pgsql') {
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS records_data_gin ON records USING GIN (data)');
            } catch (\Exception $e) {
                // Ignore if fails
            }
        }
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

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex('receipts_status_index');
            $table->dropIndex('receipts_created_by_index');
            $table->dropIndex('receipts_register_id_index');
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex('workflow_executions_workflow_version_id_index');
            $table->dropIndex('workflow_executions_started_by_index');
            $table->dropIndex('workflow_executions_receipt_id_index');
            $table->dropIndex('workflow_executions_status_index');
        });

        Schema::table('official_fees', function (Blueprint $table) {
            $table->dropIndex('official_fees_is_active_index');
            $table->dropIndex('official_fees_effective_from_index');
        });

        Schema::table('fee_versions', function (Blueprint $table) {
            $table->dropIndex('fee_versions_fee_id_effective_from_index');
            $table->dropIndex('fee_versions_effective_to_index');
        });

        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropIndex('validation_rules_workflow_version_id_is_active_index');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_record_number_index');
        });

        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropIndex('help_articles_category_index');
        });

        Schema::table('template_rules', function (Blueprint $table) {
            $table->dropIndex('template_rules_trigger_field_id_index');
            $table->dropIndex('template_rules_target_field_id_index');
        });

        Schema::table('register_fields', function (Blueprint $table) {
            $table->dropIndex('register_fields_register_id_index');
        });

        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropIndex('workflow_fields_workflow_version_id_index');
            $table->dropIndex('workflow_fields_step_id_index');
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->dropIndex('workflow_rules_workflow_version_id_is_active_index');
        });

        Schema::table('workflow_execution_events', function (Blueprint $table) {
            $table->dropIndex('workflow_execution_events_event_type_index');
        });

        Schema::table('receipt_events', function (Blueprint $table) {
            $table->dropIndex('receipt_events_event_type_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('DROP INDEX IF EXISTS records_data_gin');
            } catch (\Exception $e) {
                // Ignore if fails
            }
        }
    }
};
