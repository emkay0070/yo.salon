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
        Schema::create('salon_booking_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('salon_id');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            
            // Policy Overrides (overrides provider defaults)
            $table->json('availability_policy_override')->nullable();
            $table->json('assignment_policy_override')->nullable();
            $table->json('payment_policy_override')->nullable();
            $table->json('cancellation_policy_override')->nullable();
            $table->json('approval_policy_override')->nullable();
            $table->json('booking_policy_override')->nullable();
            
            $table->timestamps();
            
            $table->unique('salon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salon_booking_config');
    }
};
