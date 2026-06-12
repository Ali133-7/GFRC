<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            // Ensure all foreign key columns exist and are nullable
            if (!Schema::hasColumn('dashboards', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('scope');
            }
            if (!Schema::hasColumn('dashboards', 'department_id')) {
                $table->uuid('department_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('dashboards', 'role_id')) {
                $table->uuid('role_id')->nullable()->after('department_id');
            }
            if (!Schema::hasColumn('dashboards', 'organization_id')) {
                $table->uuid('organization_id')->nullable()->after('role_id');
            }
            if (!Schema::hasColumn('dashboards', 'template_id')) {
                $table->uuid('template_id')->nullable()->after('organization_id');
            }
            if (!Schema::hasColumn('dashboards', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('template_id');
            }
            if (!Schema::hasColumn('dashboards', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('dashboards', 'version')) {
                $table->integer('version')->default(1)->after('status');
            }
            if (!Schema::hasColumn('dashboards', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn([
                'user_id',
                'department_id',
                'role_id',
                'organization_id',
                'template_id',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
