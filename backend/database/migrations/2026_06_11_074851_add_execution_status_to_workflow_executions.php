<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add execution_status and execution_error columns to workflow_executions table.
     */
    public function up(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->string('execution_status', 20)->default('IDLE')->after('status');
            $table->text('execution_error')->nullable()->after('execution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropColumn(['execution_status', 'execution_error']);
        });
    }
};
