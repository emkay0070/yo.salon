<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create booking_resolutions table.
     *
     * This table stores structured outcome/resolution/follow-up records for bookings
     * that have entered the needs_resolution operational state or otherwise require
     * formal resolution (cancellation, rescheduling, late completion, etc.).
     *
     * Multiple resolutions per booking are NOT supported - each booking can have
     * at most one final resolution. This is enforced at the application level.
     */
    public function up(): void
    {
        Schema::create('booking_resolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_id');
            
            // Resolution outcome
            $table->string('outcome'); // NO_SHOW, CANCELLED, RESCHEDULED, COMPLETED_LATE, CUSTOMER_ARRIVED
            $table->string('reason_code'); // CUSTOMER_DID_NOT_ARRIVE, CUSTOMER_CANCELLED, CUSTOMER_REQUESTED_RESCHEDULE, etc.
            $table->text('notes')->nullable();
            
            // Actor information
            $table->string('actor_type')->nullable(); // CUSTOMER, SPECIALIST, STAFF, PROVIDER, SYSTEM
            $table->uuid('actor_id')->nullable(); // Nullable because SYSTEM may not map to a user
            
            // Resolution timestamp
            $table->timestamp('resolved_at');
            
            // Follow-up tracking
            $table->boolean('follow_up_required')->default(false);
            $table->timestamp('follow_up_at')->nullable();
            $table->string('follow_up_status')->nullable(); // PENDING, SENT, RESPONDED, COMPLETED
            $table->text('follow_up_notes')->nullable();
            
            $table->timestamps();
            
            // Foreign key to bookings
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            
            // Indexes for efficient queries
            $table->index('booking_id');
            $table->index('resolved_at');
            $table->index(['outcome', 'resolved_at']);
            $table->index('follow_up_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_resolutions');
    }
};
