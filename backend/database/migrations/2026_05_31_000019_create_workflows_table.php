<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 200);
            $table->string('name_en', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('current_version')->default(1);
            $table->integer('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
