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
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'])->default('salon');
            $table->enum('status', ['pending', 'active', 'suspended', 'inactive'])->default('pending');
            
            // Basic info
            $table->string('display_name');
            $table->string('slug')->unique()->nullable();
            $table->text('description')->nullable();
            
            // Branding
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            
            // Contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // Location (JSON for flexibility)
            $table->json('location')->nullable();
            
            // Social links (JSON)
            $table->json('social_links')->nullable();
            
            // Settings (JSON for flexibility)
            $table->json('settings')->nullable();
            
            // Ratings
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            
            // Status
            $table->boolean('active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('slug');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
