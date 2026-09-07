<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1B: Backfill specialist_assignments.staff_id
 *
 * Because V1 used the same UUID for Staff and Specialist, we can safely resolve:
 *   assignment.staff_id = Staff::find(assignment.specialist_id)?.id
 *
 * This migration is SAFE — it only writes a value where the Staff record exists.
 * It does NOT blindly copy the specialist_id. It verifies each assignment.
 *
 * After this migration runs, execute a verification check before Phase 1D.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                UPDATE specialist_assignments sa
                SET staff_id = s.id
                FROM staff s
                WHERE sa.specialist_id = s.id
                  AND sa.staff_id IS NULL
            ");

            // Report the result
            $total = DB::table('specialist_assignments')->count();
            $withStaff = DB::table('specialist_assignments')->whereNotNull('staff_id')->count();
            $withoutStaff = DB::table('specialist_assignments')->whereNull('staff_id')->count();

            // Log anomalies — assignments with no matching staff record
            if ($withoutStaff > 0) {
                $orphaned = DB::table('specialist_assignments')
                    ->whereNull('staff_id')
                    ->select('id', 'specialist_id', 'salon_id')
                    ->get();

                Log::warning('[Phase 1B] SpecialistAssignments with no matching Staff record', [
                    'count' => $withoutStaff,
                    'assignments' => $orphaned->toArray(),
                ]);

                // Also write to artisan output via DB table comment (best we can do in migration)
                DB::statement("COMMENT ON TABLE specialist_assignments IS 'Phase 1B backfill ran: {$withoutStaff} assignment(s) could not be backfilled (orphaned specialist_id with no staff record). Review log before Phase 1D.'");
            }
        }
    }

    public function down(): void
    {
        // Reversing backfill: null out staff_id on all assignments
        DB::table('specialist_assignments')->update(['staff_id' => null]);
    }
};
