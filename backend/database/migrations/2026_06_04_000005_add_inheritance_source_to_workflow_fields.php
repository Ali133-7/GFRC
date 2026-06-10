<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->string('inheritance_source', 20)->default('register')->after('register_field_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropColumn('inheritance_source');
        });
    }
};
