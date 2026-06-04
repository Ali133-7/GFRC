<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts');
            $table->integer('version');
            $table->foreignUuid('revised_by')->constrained('users');
            $table->text('reason');
            $table->jsonb('old_snapshot');
            $table->jsonb('new_snapshot');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_revisions');
    }
};
