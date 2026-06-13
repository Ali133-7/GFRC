<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('register_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('register_fields', 'description')) {
                $table->text('description')->nullable()->after('label_en');
            }
            if (!Schema::hasColumn('register_fields', 'category')) {
                $table->string('category', 100)->nullable()->after('field_type');
            }
            if (!Schema::hasColumn('register_fields', 'is_searchable')) {
                $table->boolean('is_searchable')->default(true)->after('category');
            }
            if (!Schema::hasColumn('register_fields', 'is_filterable')) {
                $table->boolean('is_filterable')->default(true)->after('is_searchable');
            }
            if (!Schema::hasColumn('register_fields', 'is_aggregatable')) {
                $table->boolean('is_aggregatable')->default(false)->after('is_filterable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('register_fields', function (Blueprint $table) {
            $columns = ['description', 'category', 'is_searchable', 'is_filterable', 'is_aggregatable'];
            $existing = array_filter($columns, fn($col) => Schema::hasColumn('register_fields', $col));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
