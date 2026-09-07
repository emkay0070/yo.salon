<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * AssignmentScheduleConstraint
 *
 * Clips windows to the specialist's working hours for this assignment on this day,
 * and introduces a BREAK window if a break is defined.
 */
class AssignmentScheduleConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        // Use override if it exists, otherwise fall back to the recurring schedule
        $schedule = $context->scheduleOverride ?? $context->assignmentSchedule;

        if (!$schedule) {
            return $windows; // Open scheduling (inherit from salon/provider)
        }

        if (!($schedule['is_working_day'] ?? true)) {
            return collect(); // Specialist explicitly marked as not working this day
        }

        $start = $schedule['start_time'] ?? null;
        $end   = $schedule['end_time']   ?? null;

        if (!$start || !$end) {
            return $windows;
        }

        $breakStart = $schedule['break_start'] ?? null;
        $breakEnd   = $schedule['break_end']   ?? null;

        return $windows->flatMap(function (WindowDTO $window) use ($start, $end, $breakStart, $breakEnd) {
            $wStart = max($window->startsAt, $start);
            $wEnd   = min($window->endsAt, $end);

            if ($wStart >= $wEnd) {
                return [];
            }

            $baseWindow = new WindowDTO(
                assignmentId: $window->assignmentId,
                date: $window->date,
                startsAt: $wStart,
                endsAt: $wEnd,
                status: 'AVAILABLE',
            );

            // Split into two windows around the break
            if ($breakStart && $breakEnd && $breakStart < $wEnd && $breakEnd > $wStart) {
                $result = [];

                if ($wStart < $breakStart) {
                    $result[] = new WindowDTO($window->assignmentId, $window->date, $wStart, $breakStart, 'AVAILABLE');
                }

                $result[] = new WindowDTO($window->assignmentId, $window->date, $breakStart, $breakEnd, 'BREAK');

                if ($breakEnd < $wEnd) {
                    $result[] = new WindowDTO($window->assignmentId, $window->date, $breakEnd, $wEnd, 'AVAILABLE');
                }

                return $result;
            }

            return [$baseWindow];
        });
    }
}
