<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            // Field effects to apply when validation matches
            // [
            //   { action: "hide"|"show"|"set_value"|"set_required"|"set_readonly", field_id: "...", value: "..." },
            //   { action: "hide", field_id: "affiliation_field" },
            //   { action: "set_value", field_id: "department", value: "auto_resolved" }
            // ]
            $table->jsonb('field_effects')->nullable()->after('lookup_config');
        });
    }

    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropColumn('field_effects');
        });
    }
};
