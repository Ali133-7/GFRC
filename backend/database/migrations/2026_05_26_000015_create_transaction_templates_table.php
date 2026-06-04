<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_templates');
    }
};
