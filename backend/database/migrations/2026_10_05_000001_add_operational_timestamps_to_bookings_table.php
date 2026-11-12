<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add operational floor timestamps to the bookings table.
 *
 * These columns track the real-world arrival → waiting → service lifecycle
 * that happens on the salon floor. They are separate from:
 *   - booking_service.started_at / actual_end_at  (the service timer — who is being treated)
 *   - bookings.status                             (the reservation state machine)
 *
 * The status column already supports arbitrary string values (no DB enum),
 * so no schema change is needed to add 'arrived' / 'waiting' to the state machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // When the client physically arrived at the salon
            $table->timestamp('arrived_at')->nullable()->after('slot_locked_until');

            // When the client was placed in the waiting queue
            // (specialist busy, on break, or client arrived early)
            $table->timestamp('waiting_since')->nullable()->after('arrived_at');

            // Specialist-level queue position for this booking (1 = next up)
            // NULL = not yet queued
            $table->unsignedSmallInteger('queue_position')->nullable()->after('waiting_since');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'waiting_since', 'queue_position']);
        });
    }
};
