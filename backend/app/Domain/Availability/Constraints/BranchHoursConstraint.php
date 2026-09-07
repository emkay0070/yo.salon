<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * BranchHoursConstraint
 *
 * Clips available windows to the salon branch's opening hours.
 * Inherits provider hours if no salon schedule is defined.
 */
class BranchHoursConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        $schedule = $context->salonSchedule;

        // No branch-level override — inherit from provider (pass through)
        if (!$schedule) {
            return $windows;
        }

        if ($schedule['is_closed'] ?? false) {
            return collect(); // Branch closed → no availability
        }

        $open  = $schedule['open_time']  ?? null;
        $close = $schedule['close_time'] ?? null;

        if (!$open || !$close) {
            return $windows;
        }

        return $windows->map(function (WindowDTO $window) use ($open, $close) {
            return new WindowDTO(
                assignmentId: $window->assignmentId,
                date: $window->date,
                startsAt: max($window->startsAt, $open),
                endsAt: min($window->endsAt, $close),
                status: $window->status,
            );
        })->filter(fn (WindowDTO $w) => $w->startsAt < $w->endsAt);
    }
}
