<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Favoritable entity (polymorphic)
            $table->string('favorite_type'); // 'report', 'workflow', 'register', 'receipt', 'dashboard', 'widget'
            $table->string('favorite_id'); // ID of the favorited entity
            $table->string('favorite_name_ar');
            $table->string('favorite_name_en')->nullable();
            
            // Categorization
            $table->string('category')->nullable(); // User-defined category
            $table->integer('sort_order')->default(0);
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional data about the favorite
            $table->json('quick_access_config')->nullable(); // Quick access settings
            
            $table->timestamps();
            
            $table->index(['user_id', 'favorite_type']);
            $table->index(['user_id', 'category']);
            $table->index(['favorite_type', 'favorite_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
