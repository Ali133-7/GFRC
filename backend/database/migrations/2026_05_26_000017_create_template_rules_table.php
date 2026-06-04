<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('transaction_templates')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->foreignUuid('trigger_field_id')->constrained('register_fields');
            $table->string('trigger_operator')->default('equals'); // equals, not_equals, contains, gt, lt
            $table->string('trigger_value');
            $table->foreignUuid('target_field_id')->constrained('register_fields');
            $table->string('action')->default('set_value'); // set_value, set_amount, hide, show
            $table->text('action_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_rules');
    }
};
