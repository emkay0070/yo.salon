<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * salon_service controls which services a specific branch offers,
     * and allows per-branch overrides on price, duration, and booking settings.
     * 
     * Services belong to a Provider (the catalog).
     * salon_service is the per-branch availability layer on top.
     */
    public function up(): void
    {
        Schema::create('salon_service', function (Blueprint $table) {
            // Keys
            $table->uuid('salon_id');
            $table->uuid('service_id');
            $table->primary(['salon_id', 'service_id']);

            // Availability (null = inherit from provider)
            $table->boolean('is_available')->nullable()->default(null);
            $table->boolean('online_booking_enabled')->nullable()->default(null);

            // Branch-level overrides (null = use provider-level default)
            $table->decimal('price_override', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_before_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_after_override')->nullable(); // minutes

            // Booking policy overrides
            $table->unsignedSmallInteger('min_booking_notice_override')->nullable(); // hours
            $table->unsignedSmallInteger('cancellation_cutoff_override')->nullable(); // hours
            $table->boolean('deposit_required_override')->nullable(); // null = inherit

            // Notes
            $table->text('notes')->nullable(); // e.g. "Only available on weekdays at this branch"

            $table->timestamps();

            // Foreign Keys
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            // Indexes
            $table->index(['salon_id', 'is_available']);
            $table->index(['service_id', 'is_available']);
        });

        // Add type to salons table (physical, mobile, popup)
        Schema::table('salons', function (Blueprint $table) {
            $table->enum('type', ['physical', 'mobile', 'popup'])->default('physical')->after('slug');
            $table->boolean('accepting_online_bookings')->default(true)->after('is_open');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salon_service');
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn(['type', 'accepting_online_bookings']);
        });
    }
};
