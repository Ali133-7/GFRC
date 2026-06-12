<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main Reports Definition Table
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('data_source'); // table/model name
            $table->json('configuration'); // Full report config
            $table->string('type')->default('custom'); // custom, system, analytics
            $table->string('visibility')->default('private'); // private, shared, public, role, department
            $table->string('scope')->default('user'); // user, role, department, system
            $table->uuid('created_by');
            $table->uuid('register_id')->nullable(); // Related financial register
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System reports can't be deleted
            $table->integer('version')->default(1);
            $table->uuid('parent_report_id')->nullable(); // For versioning
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['data_source', 'is_active']);
            $table->index(['visibility', 'scope']);
            $table->index(['created_by', 'is_active']);
            $table->index(['register_id', 'type']);
        });

        // Report Fields Configuration
        Schema::create('report_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('field_name'); // Database field
            $table->string('field_label'); // Display label
            $table->string('field_label_ar')->nullable();
            $table->string('field_type'); // string, number, date, boolean, currency
            $table->string('table_alias')->nullable(); // For joins
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->boolean('is_groupable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('formatting')->nullable(); // Date format, number format, etc
            $table->json('permissions')->nullable(); // Field-level permissions
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'is_visible']);
        });

        // Report Filters Configuration
        Schema::create('report_filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('filter_name');
            $table->string('filter_label');
            $table->string('filter_label_ar')->nullable();
            $table->string('field_name'); // Database field to filter
            $table->string('filter_type'); // date_range, select, multi_select, text, number, boolean
            $table->string('operator')->default('='); // =, !=, >, <, >=, <=, like, in, between
            $table->json('options')->nullable(); // For select/multi-select
            $table->json('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'filter_type']);
        });

        // Report Aggregations Configuration
        Schema::create('report_aggregations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('field_name');
            $table->string('aggregation_type'); // SUM, COUNT, AVG, MIN, MAX, CUSTOM
            $table->string('alias'); // Display name for aggregation
            $table->string('alias_ar')->nullable();
            $table->json('expression')->nullable(); // For custom expressions
            $table->string('format')->nullable(); // currency, percentage, number
            $table->integer('decimal_places')->default(2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'aggregation_type']);
        });

        // Report Grouping Configuration
        Schema::create('report_groupings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('field_name');
            $table->string('field_label');
            $table->integer('sort_order')->default(0);
            $table->boolean('show_subtotals')->default(true);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'sort_order']);
        });

        // Report Charts Configuration
        Schema::create('report_charts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('chart_name');
            $table->string('chart_type'); // bar, line, pie, area, donut, scatter
            $table->json('configuration'); // Chart.js / ECharts config
            $table->string('x_axis_field')->nullable();
            $table->string('y_axis_field')->nullable();
            $table->string('group_by_field')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'chart_type']);
        });

        // Report Executions (Audit & Cache)
        Schema::create('report_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->uuid('user_id');
            $table->json('filters_applied'); // Actual filters used
            $table->integer('rows_returned')->default(0);
            $table->integer('execution_time_ms')->default(0);
            $table->string('cache_key')->nullable();
            $table->boolean('from_cache')->default(false);
            $table->string('export_format')->nullable(); // json, pdf, excel
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['report_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['cache_key']);
        });

        // Report Permissions (Fine-grained)
        Schema::create('report_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('permissionable_type'); // App\Models\Role, App\Models\User, App\Models\Department
            $table->uuid('permissionable_id');
            $table->string('permission_type'); // view, execute, export, edit, delete
            $table->json('field_restrictions')->nullable(); // Fields they can/can't see
            $table->json('filter_restrictions')->nullable(); // Filters they can use
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['permissionable_type', 'permissionable_id']);
            $table->unique(['report_id', 'permissionable_type', 'permissionable_id', 'permission_type']);
        });

        // Saved Report Templates (User-specific)
        Schema::create('user_saved_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('report_id')->nullable(); // Null for custom ad-hoc reports
            $table->string('name');
            $table->json('configuration'); // User's custom config
            $table->json('filters')->nullable(); // Saved filter presets
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('report_id')->references('id')->on('reports')->setNullOnDelete();
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_saved_reports');
        Schema::dropIfExists('report_permissions');
        Schema::dropIfExists('report_executions');
        Schema::dropIfExists('report_charts');
        Schema::dropIfExists('report_groupings');
        Schema::dropIfExists('report_aggregations');
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_fields');
        Schema::dropIfExists('reports');
    }
};
