<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->string('expectation', 20)->nullable()->after('lookup_config');
        });
    }

    public function down(): void
    {
        Schema::table('validation_rules', function (Blueprint $table) {
            $table->dropColumn('expectation');
        });
    }
};
