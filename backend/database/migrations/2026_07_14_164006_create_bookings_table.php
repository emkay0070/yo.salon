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
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('location_id');
            $table->uuid('customer_id');
            $table->uuid('staff_id')->nullable();
            $table->uuid('service_id');
            $table->date('date');
            $table->string('time'); // Time slot (e.g., "10:00", "14:30")
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('service_id')->references('id')->on('services');

            $table->index('provider_id');
            $table->index('location_id');
            $table->index('customer_id');
            $table->index('staff_id');
            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
