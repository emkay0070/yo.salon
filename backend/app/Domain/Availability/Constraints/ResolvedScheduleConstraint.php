<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ResolvedScheduleConstraint implements AvailabilityConstraint
{
    public function apply(Collection $windows, AvailabilityContext $context): Collection
    {
        $schedule = $context->resolvedSchedule;

        if (!$schedule || $schedule->isClosed) {
            return collect(); // Closed on this day
        }

        return $windows->map(function (WindowDTO $window) use ($schedule) {
            // Trim the window to the open/close bounds
            $startsAt = max($window->startsAt, $schedule->openTime ?? '00:00:00');
            $endsAt = min($window->endsAt, $schedule->closeTime ?? '23:59:59');

            if ($startsAt >= $endsAt) {
                return null;
            }

            // Handle break time if present
            if ($schedule->breakStart && $schedule->breakEnd) {
                // If break time intersects, split the window
                if ($schedule->breakStart > $startsAt && $schedule->breakEnd < $endsAt) {
                    return [
                        $window->clone(startsAt: $startsAt, endsAt: $schedule->breakStart),
                        $window->clone(startsAt: $schedule->breakEnd, endsAt: $endsAt),
                    ];
                }
                
                // If window starts during break
                if ($startsAt >= $schedule->breakStart && $startsAt < $schedule->breakEnd) {
                    $startsAt = $schedule->breakEnd;
                }
                
                // If window ends during break
                if ($endsAt > $schedule->breakStart && $endsAt <= $schedule->breakEnd) {
                    $endsAt = $schedule->breakStart;
                }
                
                if ($startsAt >= $endsAt) {
                    return null;
                }
            }

            return $window->clone(startsAt: $startsAt, endsAt: $endsAt);
        })
        ->flatten()
        ->filter()
        ->values();
    }
}
