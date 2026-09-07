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
        Schema::create('provider_booking_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            
            // Availability Policy
            $table->json('availability_policy')->nullable();
            
            // Assignment Policy
            $table->json('assignment_policy')->nullable();
            
            // Payment Policy
            $table->json('payment_policy')->nullable();
            
            // Cancellation Policy
            $table->json('cancellation_policy')->nullable();
            
            // Approval Policy
            $table->json('approval_policy')->nullable();
            
            // Booking Policy
            $table->json('booking_policy')->nullable();
            
            $table->timestamps();
            
            $table->unique('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_booking_config');
    }
};
