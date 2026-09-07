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
        Schema::create('service_booking_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            
            // Policy Overrides (overrides salon/provider defaults)
            $table->json('availability_policy_override')->nullable();
            $table->json('assignment_policy_override')->nullable();
            $table->json('payment_policy_override')->nullable();
            $table->json('cancellation_policy_override')->nullable();
            $table->json('approval_policy_override')->nullable();
            $table->json('booking_policy_override')->nullable();
            
            // Service-specific requirements
            $table->json('service_requirements')->nullable();
            
            $table->timestamps();
            
            $table->unique('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_booking_config');
    }
};
