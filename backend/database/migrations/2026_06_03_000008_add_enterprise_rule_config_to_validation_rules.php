<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            // Main rule configuration (enterprise format)
            // {
            //   "conditions": [...],
            //   "actions": [...],
            //   "else_actions": [...],
            //   "cases": [...],
            //   "conflict_resolution": "highest_priority"
            // }
            $table->jsonb('rule_config')->nullable()->after('trigger_conditions');

            // Rule priority (1-10000, higher = evaluated first)
            $table->integer('priority')->default(5000)->after('sort_order');

            // Rule category
            $table->string('category', 50)->default('validation')->after('validation_type');
        });
    }

    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropColumn(['rule_config', 'priority', 'category']);
        });
    }
};
