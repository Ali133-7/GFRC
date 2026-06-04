<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            // Routing configuration for field_existence_check
            // {
            //   "on_match": {
            //     "action": "route_workflow|block|warn",
            //     "target_workflow_id": "uuid|null",
            //     "target_step_id": "uuid|null",
            //     "message_ar": "...",
            //     "actions": ["view_existing", "continue_update", "start_renewal"]
            //   },
            //   "on_not_found": {
            //     "action": "continue_workflow",
            //     "message_ar": "..."
            //   }
            // }
            $table->jsonb('route_config')->nullable()->after('sql_condition');

            // Lookup strategy for field-aware queries
            // { "database_column": "file_number", "lookup_strategy": "exact|contains|starts_with" }
            $table->jsonb('lookup_config')->nullable()->after('route_config');

            // The specific workflow field that triggers this rule
            $table->foreignUuid('trigger_field_id')->nullable()->after('target_register_id');

            // Index for fast lookups
            $table->index(['validation_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropIndex(['validation_type', 'is_active']);
            $table->dropColumn(['route_config', 'lookup_config', 'trigger_field_id']);
        });
    }
};
