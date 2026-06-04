<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers')->onDelete('cascade');
            $table->string('record_number', 100)->nullable();
            $table->jsonb('data')->nullable(); // Dynamic field values: {"file_number": "1337", "name": "..."}
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();

            $table->index(['register_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
