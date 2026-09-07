<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * TimeOffConstraint
 *
 * Removes or marks windows as LEAVE where specialist time-off is recorded.
 */
class TimeOffConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        if ($context->timeOff->isEmpty()) {
            return $windows;
        }

        return $windows->flatMap(function (WindowDTO $window) use ($context) {
            $remaining = collect([$window]);

            foreach ($context->timeOff as $leave) {
                // Full day leave — mark entire window as LEAVE
                if (!$leave->start_time && !$leave->end_time) {
                    return [new WindowDTO($window->assignmentId, $window->date, $window->startsAt, $window->endsAt, 'LEAVE')];
                }

                $leaveStart = $leave->start_time;
                $leaveEnd   = $leave->end_time;

                $remaining = $remaining->flatMap(function (WindowDTO $w) use ($leaveStart, $leaveEnd) {
                    if ($leaveStart >= $w->endsAt || $leaveEnd <= $w->startsAt) {
                        return [$w]; // No overlap
                    }

                    $result = [];

                    if ($w->startsAt < $leaveStart) {
                        $result[] = new WindowDTO($w->assignmentId, $w->date, $w->startsAt, $leaveStart, $w->status);
                    }

                    $result[] = new WindowDTO($w->assignmentId, $w->date, $leaveStart, $leaveEnd, 'LEAVE');

                    if ($leaveEnd < $w->endsAt) {
                        $result[] = new WindowDTO($w->assignmentId, $w->date, $leaveEnd, $w->endsAt, $w->status);
                    }

                    return $result;
                });
            }

            return $remaining->all();
        });
    }
}
