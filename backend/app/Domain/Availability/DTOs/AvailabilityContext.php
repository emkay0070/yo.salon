<?php

namespace App\Domain\Availability\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * AvailabilityContext
 *
 * The single, immutable context object passed to every constraint in the pipeline.
 * Constraints MUST NOT query the database. All data they need lives here.
 */
class AvailabilityContext
{
    public function __construct(
        public readonly string        $assignmentId,
        public readonly string        $specialistId,
        public readonly string        $salonId,
        public readonly string        $providerId,
        public readonly CarbonImmutable $date,

        // Resolved schedule for this day (already evaluated through Provider -> Branch -> Assignment inheritance)
        public readonly ?\App\Domain\Availability\DTOs\ResolvedSchedule $resolvedSchedule,

        // Schedule override for this specific date (takes precedence over assignmentSchedule)
        public readonly ?array        $scheduleOverride,   // same shape as assignmentSchedule

        // Schedule exceptions (hierarchical: provider/salon/assignment) for this date.
        // Narrowest scope (assignment) wins over broader (provider).
        public readonly \Illuminate\Support\Collection $scheduleExceptions,

        // Time-off entries (already filtered to this date)
        public readonly Collection    $timeOff,

        // Existing bookings (already filtered to this date + assignment)
        public readonly Collection    $existingBookings,
    ) {}
}
