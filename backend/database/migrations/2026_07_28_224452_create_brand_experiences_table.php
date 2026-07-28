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
        Schema::create('brand_experiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('salon_id');
            
            // Experience Family
            $table->enum('experience_family', [
                'luxury_noir',
                'modern_glass', 
                'urban_pulse',
                'minimal_zen',
                'organic',
                'classic_barber',
                'neon_future',
                'executive'
            ])->default('luxury_noir');
            
            // Identity
            $table->string('logo')->nullable();
            
            // Brand Colors
            $table->string('primary_color')->default('#FF622B');
            $table->string('secondary_color')->default('#FF8C5A');
            $table->string('accent_color')->default('#FFD700');
            
            // Typography
            $table->enum('font_heading', ['sora', 'playfair', 'inter', 'poppins'])->default('sora');
            $table->enum('font_body', ['sora', 'playfair', 'inter', 'poppins'])->default('inter');
            
            // Background
            $table->string('background_image')->nullable();
            
            // White-label
            $table->string('custom_domain')->nullable();
            $table->boolean('white_label_enabled')->default(false);
            
            $table->timestamps();
            
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->unique('salon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_experiences');
    }
};
