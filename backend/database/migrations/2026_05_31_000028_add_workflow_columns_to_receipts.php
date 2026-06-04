<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignUuid('workflow_execution_id')->nullable()->after('register_id')->constrained('workflow_executions')->nullOnDelete();
            $table->foreignUuid('workflow_version_id')->nullable()->after('workflow_execution_id')->constrained('workflow_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_execution_id');
            $table->dropConstrainedForeignId('workflow_version_id');
        });
    }
};
