<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix schedule_mode for specialist assignments.
 * 
 * When `specialist_assignments` was given a `schedule_mode` column, it defaulted to 'INHERIT'.
 * However, the DefaultSchedulesSeeder created `assignment_schedules` for specialists without
 * updating their `schedule_mode` to 'CUSTOM'.
 * 
 * As a result, the ScheduleResolver sees 'INHERIT', ignores the assignment's own schedules,
 * and looks for a provider/salon schedule. When none is found, the schedule resolves to closed.
 * 
 * This migration finds all assignments that have their own `assignment_schedules` and updates
 * their `schedule_mode` to 'CUSTOM'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('specialist_assignments', 'schedule_mode')) {
            $affected = DB::table('specialist_assignments')
                ->where('schedule_mode', 'INHERIT')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('assignment_schedules')
                          ->whereColumn('assignment_schedules.assignment_id', 'specialist_assignments.id');
                })
                ->update(['schedule_mode' => 'CUSTOM']);

            if ($affected > 0) {
                logger()->info("schedule_mode_fix: updated {$affected} assignments to CUSTOM mode because they have assignment_schedules.");
            }
        }
    }

    public function down(): void
    {
        // No safe down operation. We don't know which ones were intentionally CUSTOM
        // versus fixed by this migration.
    }
};
