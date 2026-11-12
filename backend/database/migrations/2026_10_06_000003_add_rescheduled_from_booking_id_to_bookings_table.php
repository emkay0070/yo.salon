<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rescheduled_from_booking_id to bookings table.
     *
     * This nullable self-referencing foreign key allows tracking the original booking
     * when a customer reschedules. The new booking points to the original booking,
     * which is marked as rescheduled and resolved.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('rescheduled_from_booking_id')->nullable()->after('notes');
            
            // Self-referencing foreign key
            $table->foreign('rescheduled_from_booking_id')->references('id')->on('bookings')->onDelete('set null');
            
            // Index for querying reschedule chains
            $table->index('rescheduled_from_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('rescheduled_from_booking_id');
            $table->dropForeign(['rescheduled_from_booking_id']);
            $table->dropColumn('rescheduled_from_booking_id');
        });
    }
};
