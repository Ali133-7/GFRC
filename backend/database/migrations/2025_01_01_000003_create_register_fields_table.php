<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers');
            $table->string('name', 100);
            $table->string('label_ar', 200);
            $table->string('label_en', 200)->nullable();
            $table->string('field_type', 30)->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_financial')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('validation_rules', 500)->nullable();
            $table->string('default_value', 500)->nullable();
            $table->jsonb('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_fields');
    }
};
