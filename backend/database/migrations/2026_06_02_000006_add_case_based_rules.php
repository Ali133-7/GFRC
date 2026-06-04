<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_rules', function (Blueprint $table) {
            // Rule type: 'simple' (legacy) or 'case_based' (switch/case)
            $table->string('rule_type', 20)->default('simple')->after('name');

            // For case-based rules: the field this rule branches on
            $table->string('trigger_field_id', 100)->nullable()->after('rule_type');

            // For case-based rules: array of {value, actions, priority, compound_condition}
            $table->jsonb('cases')->nullable()->after('trigger_field_id');

            // Default case actions (executed when no case matches)
            $table->jsonb('default_actions')->nullable()->after('cases');

            // For case-based: match mode (exact, contains, pattern)
            $table->string('match_mode', 20)->default('exact')->after('default_actions');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->dropColumn([
                'rule_type',
                'trigger_field_id',
                'cases',
                'default_actions',
                'match_mode',
            ]);
        });
    }
};
