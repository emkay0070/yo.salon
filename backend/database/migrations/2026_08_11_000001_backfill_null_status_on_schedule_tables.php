<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill NULL status values on schedule tables.
 *
 * The `status` column was added to schedule tables with default('ACTIVE'),
 * but MySQL column defaults do NOT retroactively populate existing NULL rows.
 * Any schedule rows seeded or created before that migration ran have status = NULL.
 *
 * The AvailabilityContextBuilder filters all schedule queries with:
 *   WHERE status = 'ACTIVE'
 *
 * NULL rows are therefore invisible to the availability engine, causing:
 *   - Booking availability to appear as zero slots for affected specialists
 *   - The Specialist Calendar to show "Off" for every day
 *
 * This migration makes the correction reproducible across all environments
 * (local, staging, production) without relying on manual Tinker commands.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Assignment-level schedules (most likely to be affected)
        if (Schema::hasColumn('assignment_schedules', 'status')) {
            $affected = DB::table('assignment_schedules')
                ->whereNull('status')
                ->update(['status' => 'ACTIVE']);

            if ($affected > 0) {
                logger()->info("schedule_status_backfill: fixed {$affected} NULL rows in assignment_schedules");
            }
        }

        // Provider-level schedules
        if (Schema::hasColumn('provider_schedules', 'status')) {
            $affected = DB::table('provider_schedules')
                ->whereNull('status')
                ->update(['status' => 'ACTIVE']);

            if ($affected > 0) {
                logger()->info("schedule_status_backfill: fixed {$affected} NULL rows in provider_schedules");
            }
        }

        // Salon-level schedules
        if (Schema::hasColumn('salon_schedules', 'status')) {
            $affected = DB::table('salon_schedules')
                ->whereNull('status')
                ->update(['status' => 'ACTIVE']);

            if ($affected > 0) {
                logger()->info("schedule_status_backfill: fixed {$affected} NULL rows in salon_schedules");
            }
        }
    }

    public function down(): void
    {
        // Intentionally no rollback.
        // These rows were always INTENDED to be ACTIVE — this migration
        // corrects an accidental NULL. Reverting to NULL would re-break
        // availability for real specialists.
    }
};
