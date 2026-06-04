<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->jsonb('conditional_validation_rules')->nullable()->after('validation_rules');
            $table->jsonb('cross_field_validation_rules')->nullable()->after('conditional_validation_rules');
            $table->string('computed_formula', 500)->nullable()->after('calculation_formula');
            $table->jsonb('computed_dependencies')->nullable()->after('computed_formula');
            $table->boolean('is_computed')->default(false)->after('is_financial');
            $table->string('parent_field_id', 36)->nullable()->after('step_id');
            $table->string('option_source_type', 30)->nullable()->after('options');
            $table->string('option_source_config', 500)->nullable()->after('option_source_type');
            $table->jsonb('cascade_config')->nullable()->after('option_source_config');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropColumn([
                'conditional_validation_rules',
                'cross_field_validation_rules',
                'computed_formula',
                'computed_dependencies',
                'is_computed',
                'parent_field_id',
                'option_source_type',
                'option_source_config',
                'cascade_config',
            ]);
        });
    }
};
