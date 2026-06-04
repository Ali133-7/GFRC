<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number', 50)->unique();
            $table->foreignUuid('register_id')->constrained('registers');
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->decimal('total_amount', 15, 3);
            $table->enum('status', ['draft', 'pending', 'issued', 'printed', 'cancelled'])->default('draft');
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 100)->unique()->nullable();
            $table->text('qr_payload')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users');
            $table->text('cancel_reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
