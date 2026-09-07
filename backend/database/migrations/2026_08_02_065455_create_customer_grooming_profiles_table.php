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
        Schema::create('customer_grooming_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            // Hair profile
            $table->string('hair_type')->nullable();
            $table->string('preferred_hair_style')->nullable();
            $table->text('hair_concerns')->nullable();

            // Beard profile
            $table->string('beard_style')->nullable();
            $table->string('beard_products')->nullable();

            // Skin profile
            $table->string('skin_type')->nullable();
            $table->text('allergies')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_grooming_profiles');
    }
};
