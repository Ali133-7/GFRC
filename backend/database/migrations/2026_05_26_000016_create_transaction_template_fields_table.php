<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_template_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('transaction_templates')->cascadeOnDelete();
            $table->foreignUuid('register_field_id')->constrained('register_fields')->cascadeOnDelete();
            $table->string('label_override')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_readonly')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_template_fields');
    }
};
