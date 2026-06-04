<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_styles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('element_id')->constrained('template_elements')->cascadeOnDelete();
            $table->string('font_family', 100)->default('Arial');
            $table->integer('font_size')->default(12);
            $table->enum('font_weight', ['normal', 'bold', '100', '300', '400', '600', '700', '900'])->default('normal');
            $table->string('font_color', 7)->default('#000000');
            $table->string('background_color', 7)->nullable();
            $table->string('border_color', 7)->nullable();
            $table->integer('border_width')->default(0);
            $table->enum('text_align', ['left', 'center', 'right'])->default('right'); // RTL default
            $table->integer('padding_top')->default(0);
            $table->integer('padding_right')->default(0);
            $table->integer('padding_bottom')->default(0);
            $table->integer('padding_left')->default(0);
            $table->decimal('opacity', 3, 2)->default(1.00);
            $table->enum('display', ['block', 'inline', 'none'])->default('block');
            $table->integer('line_height')->default(1);
            $table->string('letter_spacing', 10)->nullable();
            $table->timestamps();

            $table->unique('element_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_styles');
    }
};
