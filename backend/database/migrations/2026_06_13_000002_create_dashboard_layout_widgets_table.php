<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_layout_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('layout_id');
            $table->string('widget_type');
            $table->json('title')->nullable();
            $table->jsonb('data_source')->nullable();
            $table->jsonb('display_config')->nullable();
            $table->uuid('register_id')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('width')->default(1);
            $table->integer('height')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('layout_id')->references('id')->on('dashboard_layouts')->cascadeOnDelete();
            $table->foreign('register_id')->references('id')->on('registers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['layout_id', 'sort_order']);
            $table->index(['widget_type', 'is_active']);
            $table->index(['register_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layout_widgets');
    }
};
