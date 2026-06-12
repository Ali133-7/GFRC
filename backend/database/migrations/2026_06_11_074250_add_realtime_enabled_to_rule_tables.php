<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add realtime_enabled column to validation_rules and workflow_rules tables.
     */
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(true)->after('is_active');
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->boolean('realtime_enabled')->default(true)->after('is_active');
        });
        
        // Update any existing NULL values to true
        DB::table('validation_rules')->whereNull('realtime_enabled')->update(['realtime_enabled' => true]);
        DB::table('workflow_rules')->whereNull('realtime_enabled')->update(['realtime_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropColumn('realtime_enabled');
        });

        Schema::table('workflow_rules', function (Blueprint $table) {
            $table->dropColumn('realtime_enabled');
        });
    }
};
