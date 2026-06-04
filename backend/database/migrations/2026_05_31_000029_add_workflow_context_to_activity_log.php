<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('workflow_version_id')->nullable()->after('batch_uuid');
            $table->uuid('execution_id')->nullable()->after('workflow_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn(['workflow_version_id', 'execution_id']);
        });
    }
};
