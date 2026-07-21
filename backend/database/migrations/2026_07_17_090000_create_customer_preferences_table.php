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
        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->uuid('preferred_staff_id')->nullable();
            $table->foreign('preferred_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->json('notification_preferences')->nullable();
            $table->json('booking_preferences')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('preferred_staff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_preferences');
    }
};
