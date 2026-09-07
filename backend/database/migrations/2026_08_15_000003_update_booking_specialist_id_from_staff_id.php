<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4: Update bookings where staff_id exists but specialist_id is missing
 * 
 * This migration updates booking records to use specialist_id as the canonical
 * professional identity, following the documented architecture where:
 * - Booking.specialist_id is the authoritative identity
 * - Staff.id === Specialist.id (preserved from July 31 migration)
 * 
 * For bookings where staff_id is set but specialist_id is null, we set
 * specialist_id = staff_id since they represent the same person.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Update bookings where staff_id is set but specialist_id is null, and only where the specialist exists
        $updated = DB::table('bookings')
            ->whereNull('specialist_id')
            ->whereNotNull('staff_id')
            ->whereIn('staff_id', function ($query) {
                $query->select('id')->from('specialists');
            })
            ->update(['specialist_id' => DB::raw('staff_id')]);

        \Log::info("Migration: Updated {$updated} bookings to set specialist_id from staff_id");
    }

    public function down(): void
    {
        // Rollback: Set specialist_id back to null for records we updated
        // This is a selective rollback - only affects records where specialist_id == staff_id
        DB::table('bookings')
            ->whereColumn('specialist_id', 'staff_id')
            ->whereNotNull('staff_id')
            ->update(['specialist_id' => null]);
    }
};
