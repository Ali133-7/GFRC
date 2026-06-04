<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('register_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('register_fields', 'is_editable')) {
                $table->boolean('is_editable')->default(true)->after('is_visible');
            }
            if (!Schema::hasColumn('register_fields', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('is_editable');
            }
            if (!Schema::hasColumn('register_fields', 'is_insured')) {
                $table->boolean('is_insured')->default(false)->after('is_locked');
            }
            if (!Schema::hasColumn('register_fields', 'insurance_value')) {
                $table->decimal('insurance_value', 15, 3)->nullable()->after('is_insured');
            }
            if (!Schema::hasColumn('register_fields', 'priority')) {
                $table->integer('priority')->default(0)->after('insurance_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('register_fields', function (Blueprint $table) {
            $columns = ['is_editable', 'is_locked', 'is_insured', 'insurance_value', 'priority'];
            $existing = array_filter($columns, fn($col) => Schema::hasColumn('register_fields', $col));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
