<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts');
            $table->foreignUuid('field_id')->constrained('register_fields');
            $table->string('field_name_snapshot', 100);
            $table->string('label_ar_snapshot', 200);
            $table->decimal('amount', 15, 3)->nullable();
            $table->text('text_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
