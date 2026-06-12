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
     * This migration is now obsolete - realtime_enabled is set in the initial migration.
     * Keeping for reference.
     */
    public function up(): void
    {
        // No-op - already handled in 2026_06_11_074250_add_realtime_enabled_to_rule_tables
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
