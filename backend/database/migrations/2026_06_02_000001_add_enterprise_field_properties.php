<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->string('field_type', 30)->default('text')->after('condition_logic');
            $table->boolean('is_editable')->default(true)->after('is_visible');
            $table->boolean('is_locked')->default(false)->after('is_editable');
            $table->boolean('is_insured')->default(false)->after('is_locked');
            $table->decimal('insurance_value', 15, 3)->nullable()->after('is_insured');
            $table->integer('priority')->default(0)->after('insurance_value');
            $table->jsonb('options')->nullable()->after('priority');
            $table->jsonb('validation_rules')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropColumn([
                'field_type',
                'is_editable',
                'is_locked',
                'is_insured',
                'insurance_value',
                'priority',
                'options',
                'validation_rules',
            ]);
        });
    }
};
