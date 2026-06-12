<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix realtime_enabled column to be NOT NULL with default true.
     * This ensures the checkbox state is properly persisted.
     */
    public function up(): void
    {
        // First, update any NULL values to true (default)
        DB::table('validation_rules')->whereNull('realtime_enabled')->update(['realtime_enabled' => true]);
        DB::table('workflow_rules')->whereNull('realtime_enabled')->update(['realtime_enabled' => true]);
        
        // Then, make the column NOT NULL with default true
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(true)->nullable(false)->change();
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(true)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(false)->nullable()->change();
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(false)->nullable()->change();
        });
    }
};
