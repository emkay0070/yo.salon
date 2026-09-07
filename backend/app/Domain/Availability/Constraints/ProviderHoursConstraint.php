<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * ProviderHoursConstraint
 *
 * Clips available windows to the provider's operating hours.
 * If the provider has no schedule for this day, returns an empty collection.
 */
class ProviderHoursConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        $schedule = $context->providerSchedule;

        if (!$schedule || ($schedule['is_closed'] ?? false)) {
            return collect(); // Provider is closed → no availability
        }

        $open  = $schedule['open_time']  ?? null;
        $close = $schedule['close_time'] ?? null;

        if (!$open || !$close) {
            return $windows; // No restriction defined — pass through
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
