<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fee_id')->constrained('official_fees')->cascadeOnDelete();
            $table->integer('version');
            $table->decimal('amount', 15, 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['fee_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_versions');
    }
};
