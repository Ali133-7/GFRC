<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropForeign(['register_field_id']);
            $table->foreignUuid('register_field_id')->nullable()->change();
            $table->foreign('register_field_id')->references('id')->on('register_fields')->nullOnDelete();
        });

        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->string('custom_name', 100)->nullable()->after('label_override');
            $table->string('custom_label', 200)->nullable()->after('custom_name');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_fields', function (Blueprint $table) {
            $table->dropColumn(['custom_name', 'custom_label']);
        });
    }
};
