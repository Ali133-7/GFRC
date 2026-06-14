<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'is_default', 'is_active']);
            $table->index(['role_id', 'is_default', 'is_active']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE dashboard_layouts ADD CONSTRAINT dashboard_layouts_user_or_role_check CHECK ((user_id IS NOT NULL) OR (role_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }
};
