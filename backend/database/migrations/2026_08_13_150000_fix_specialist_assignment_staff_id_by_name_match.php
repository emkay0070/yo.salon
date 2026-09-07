<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1B Correction: Match Specialist assignments to Staff records by name + salon.
 *
 * Context:
 *   Staff and Specialists were created through independent paths (seeder vs onboarding).
 *   Their UUIDs do NOT match. The backfill migration assumed they would match — it was wrong.
 *
 * This migration resolves the linkage by matching: name + salon_id (for salon-assigned staff).
 * For independent workspace specialists (Jake, Jimmy), staff_id stays NULL intentionally —
 * they are workspace operators with no salon-local employment record.
 *
 * Architecture note:
 *   staff_id on SpecialistAssignment is intentionally nullable for independent specialists
 *   who operate their own workspace and have no salon-local Staff record.
 *   The NOT NULL constraint (Phase 1D) will NOT apply to these cases.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get all assignments that still have no staff_id
        $unlinkedAssignments = DB::table('specialist_assignments')
            ->whereNull('staff_id')
            ->get();

        $linked = 0;
        $skipped = [];

        foreach ($unlinkedAssignments as $assignment) {
            // Get specialist name
            $specialist = DB::table('specialists')
                ->where('id', $assignment->specialist_id)
                ->first(['id', 'name']);

            if (!$specialist) continue;

            // Attempt to match a Staff record with the same name at the same salon
            $matchingStaff = DB::table('staff')
                ->where('salon_id', $assignment->salon_id)
                ->whereRaw('LOWER(name) = LOWER(?)', [$specialist->name])
                ->first(['id', 'name', 'salon_id']);

            if ($matchingStaff) {
                DB::table('specialist_assignments')
                    ->where('id', $assignment->id)
                    ->update(['staff_id' => $matchingStaff->id]);

                Log::info("[Phase 1B Fix] Linked Specialist '{$specialist->name}' ({$specialist->id}) → Staff ({$matchingStaff->id}) at salon {$assignment->salon_id}");
                $linked++;
            } else {
                // This is an independent workspace specialist — staff_id intentionally NULL
                $skipped[] = [
                    'assignment_id' => $assignment->id,
                    'specialist_id' => $assignment->specialist_id,
                    'specialist_name' => $specialist->name,
                    'salon_id' => $assignment->salon_id,
                    'reason' => 'No matching Staff record by name+salon — likely an independent workspace specialist',
                ];

                Log::info("[Phase 1B Fix] Skipped Specialist '{$specialist->name}' ({$specialist->id}) — no matching Staff at salon {$assignment->salon_id}. Treated as independent workspace operator.");
            }
        }

        Log::info("[Phase 1B Fix] Complete. Linked: {$linked}, Skipped (independent): " . count($skipped));
    }

    public function down(): void
    {
        // Re-null the staff_ids that were set by name matching
        // (Cannot reverse perfectly; best effort: null anything not set by Phase 1B backfill)
        DB::table('specialist_assignments')->update(['staff_id' => null]);
    }
};
