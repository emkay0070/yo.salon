<?php

namespace App\Domain\Availability\Resolvers;

use App\Domain\Availability\DTOs\ResolvedSchedule;
use App\Models\ProviderSchedule;
use App\Models\SalonSchedule;
use App\Models\AssignmentSchedule;

/**
 * ScheduleResolver
 *
 * Takes fully-loaded schedule OBJECTS (not arrays, not DB queries)
 * and walks the inheritance tree:
 *
 *     ProviderSchedule  ← SalonSchedule  ← AssignmentSchedule
 *
 * Depending on schedule_mode (INHERIT / CUSTOM) of each layer, resolves to a single
 * immutable ResolvedSchedule for that day.
 *
 * This class MUST NOT touch the database. All data must be pre-loaded by AvailabilityContextBuilder.
 */
final class ScheduleResolver
{
    public function resolve(
        ?ProviderSchedule $providerSchedule,
        ?SalonSchedule    $salonSchedule,
        string            $salonMode,
        ?AssignmentSchedule $assignmentSchedule,
        string            $assignmentMode,
    ): ?ResolvedSchedule {

        // 1. Resolve Branch Level
        $branchLayer = null;

        if ($salonMode === 'CUSTOM' && $salonSchedule !== null) {
            $branchLayer = $salonSchedule;
        } else {
            // INHERIT from provider
            $branchLayer = $providerSchedule;
        }

        // 2. Resolve Assignment Level
        $assignmentLayer = null;

        if ($assignmentMode === 'CUSTOM' && $assignmentSchedule !== null) {
            $assignmentLayer = $assignmentSchedule;
        } else {
            // INHERIT from branch (which already inherited from provider if needed)
            $assignmentLayer = $branchLayer;
        }

        // 3. Translate to immutable DTO
        if ($assignmentLayer === null) {
            return null;
        }

        $arr = $assignmentLayer->toArray();

        return new ResolvedSchedule(
            isClosed:   $arr['is_closed'] ?? !($arr['is_working_day'] ?? true),
            openTime:   $arr['open_time'] ?? $arr['start_time'] ?? null,
            closeTime:  $arr['close_time'] ?? $arr['end_time'] ?? null,
            breakStart: $arr['break_start'] ?? null,
            breakEnd:   $arr['break_end'] ?? null,
        );
    }
}
