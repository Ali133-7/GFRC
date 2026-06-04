<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            // Multiple trigger conditions for field_existence_check
            // [
            //   { field_id: "...", operator: "exact"|"contains"|"starts_with"|"ends_with"|"not_equals"|"empty"|"not_empty", value: "..." },
            //   { field_id: "status_field", operator: "exact", value: "active" },
            //   { field_id: "department_field", operator: "contains", value: "finance" }
            // ]
            $table->jsonb('trigger_conditions')->nullable()->after('trigger_field_id');
        });
    }

    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropColumn('trigger_conditions');
        });
    }
};
