<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('receipt_templates')->cascadeOnDelete();
            $table->foreignUuid('field_id')->nullable()->constrained('register_fields')->nullOnDelete();
            $table->enum('element_type', ['field', 'text', 'divider', 'qr', 'signature', 'total', 'image', 'spacer'])->default('field');
            $table->string('label', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('x')->default(0); // pixel or percentage
            $table->integer('y')->default(0); // pixel or percentage
            $table->integer('width')->default(100);
            $table->integer('height')->default(30);
            $table->boolean('is_visible')->default(true);
            $table->jsonb('metadata')->nullable(); // custom data per element
            $table->timestamps();

            $table->index('template_id');
            $table->index('field_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_elements');
    }
};
