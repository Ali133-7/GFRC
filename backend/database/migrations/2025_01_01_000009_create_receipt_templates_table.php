<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('layout_type', ['portrait', 'landscape', 'custom'])->default('portrait');
            $table->integer('page_width')->default(210); // mm
            $table->integer('page_height')->default(297); // mm
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->jsonb('metadata')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('register_id');
            $table->index('is_default');
            $table->index(['register_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
