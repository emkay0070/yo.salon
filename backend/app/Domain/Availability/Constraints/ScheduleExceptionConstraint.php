<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * ScheduleExceptionConstraint
 *
 * Applies one-off or recurring schedule exceptions (HOLIDAY, VACATION, CLOSURE,
 * TRAINING, POWER_OUTAGE, EVENT, OTHER) on top of the recurring weekly schedule.
 *
 * Precedence rules (narrowest scope wins):
 *   assignment exception  >  salon exception  >  provider exception
 *
 * Behaviour:
 *   - If $exception->is_closed === true : mark entire window as CLOSED (drop it)
 *   - Otherwise                          : trim window to the custom open/close hours
 *                                         of the exception, and clip break around it.
 */
class ScheduleExceptionConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        if ($context->scheduleExceptions->isEmpty()) {
            return $windows;
        }

        // Narrowest scope (assignment) wins. forAssignment() already orders
        // assignment → salon → provider. Grab the first one that actually applies
        // (in a tie by scope_level, the one with custom hours takes precedence).
        $winning = $context->scheduleExceptions
            ->sort(function ($a, $b) {
                $rank = ['assignment' => 3, 'salon' => 2, 'provider' => 1];
                return ($rank[$b->scope_level] ?? 0) <=> ($rank[$a->scope_level] ?? 0);
            })
            ->first();

        if (!$winning) {
            return $windows;
        }

        if ($winning->is_closed) {
            // Treat closed all-day like public holiday. Customer sees "closed"
            // via the normal closed-result flow upstream.
            return collect();
        }

        // Custom-hour exception (e.g. half-day training).
        $open  = $winning->open_time;
        $close = $winning->close_time;

        if (!$open || !$close) {
            return $windows;
        }

        $breakStart = $winning->break_start;
        $breakEnd   = $winning->break_end;

        return $windows->flatMap(function (WindowDTO $window) use ($open, $close, $breakStart, $breakEnd) {
            $wStart = max($window->startsAt, $open);
            $wEnd   = min($window->endsAt, $close);

            if ($wStart >= $wEnd) {
                return [];
            }

            $base = new WindowDTO(
                assignmentId: $window->assignmentId,
                date:         $window->date,
                startsAt:     $wStart,
                endsAt:       $wEnd,
                status:       'AVAILABLE',
            );

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

            return [$base];
        });
    }
}
