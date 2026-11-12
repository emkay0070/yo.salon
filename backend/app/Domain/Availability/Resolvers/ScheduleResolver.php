<?php

namespace App\Domain\Availability\Resolvers;

use App\Domain\Availability\DTOs\ResolvedSchedule;
use App\Models\ProviderSchedule;
use App\Models\LocationSchedule;
use App\Models\AssignmentSchedule;

/**
 * ScheduleResolver
 *
 * Takes fully-loaded schedule OBJECTS (not arrays, not DB queries)
 * and walks the inheritance tree:
 *
 *     ProviderSchedule  ← LocationSchedule  ← AssignmentSchedule
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
        ?LocationSchedule $locationSchedule,
        string            $locationMode,
        ?AssignmentSchedule $assignmentSchedule,
        string            $assignmentMode,
    ): ?ResolvedSchedule {

        \Log::info('ScheduleResolver: resolve called', [
            'locationMode' => $locationMode,
            'assignmentMode' => $assignmentMode,
            'hasProviderSchedule' => $providerSchedule ? true : false,
            'hasLocationSchedule' => $locationSchedule ? true : false,
            'hasAssignmentSchedule' => $assignmentSchedule ? true : false,
        ]);

        // 1. Resolve Branch Level
        $branchLayer = null;

        if ($locationMode === 'CUSTOM' && $locationSchedule !== null) {
            $branchLayer = $locationSchedule;
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

        \Log::info('ScheduleResolver: layer resolution', [
            'branchLayer_type' => $branchLayer ? get_class($branchLayer) : 'null',
            'assignmentLayer_type' => $assignmentLayer ? get_class($assignmentLayer) : 'null',
        ]);

        // 3. Translate to immutable DTO
        if ($assignmentLayer === null) {
            \Log::info('ScheduleResolver: assignmentLayer is null, returning null');
            return null;
        }

        $arr = $assignmentLayer->toArray();

        \Log::info('ScheduleResolver: assignmentLayer data', [
            'data' => $arr,
        ]);

        $resolved = new ResolvedSchedule(
            isClosed:   $arr['is_closed'] ?? !($arr['is_working_day'] ?? true),
            openTime:   $arr['open_time'] ?? $arr['start_time'] ?? null,
            closeTime:  $arr['close_time'] ?? $arr['end_time'] ?? null,
            breakStart: $arr['break_start'] ?? null,
            breakEnd:   $arr['break_end'] ?? null,
        );

        \Log::info('ScheduleResolver: resolved schedule', [
            'isClosed' => $resolved->isClosed,
            'openTime' => $resolved->openTime,
            'closeTime' => $resolved->closeTime,
        ]);

        return $resolved;
    }
}
