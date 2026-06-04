<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_fees', function (Blueprint $table) {
            $table->string('fee_code', 50)->nullable()->unique()->after('id');
            $table->integer('version')->default(1)->after('fee_code');
        });
    }

    public function down(): void
    {
        Schema::table('official_fees', function (Blueprint $table) {
            $table->dropColumn(['fee_code', 'version']);
        });
    }
};
