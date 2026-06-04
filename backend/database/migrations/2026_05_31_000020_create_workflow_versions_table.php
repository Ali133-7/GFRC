<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->integer('version');
            $table->string('status', 20)->default('draft'); // draft, active, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users');
            $table->text('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
