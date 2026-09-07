<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * BookingConflictConstraint
 *
 * Removes or marks windows as BOOKED where existing bookings overlap.
 */
class BookingConflictConstraint implements AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        if ($context->existingBookings->isEmpty()) {
            return $windows;
        }

        return $windows->flatMap(function (WindowDTO $window) use ($context) {
            $remaining = collect([$window]);

            foreach ($context->existingBookings as $booking) {
                $bookingStart = $booking->time;
                $bookingEnd   = $booking->end_time ?? null;

                if (!$bookingStart || !$bookingEnd) {
                    continue;
                }

                $remaining = $remaining->flatMap(function (WindowDTO $w) use ($bookingStart, $bookingEnd) {
                    if ($bookingStart >= $w->endsAt || $bookingEnd <= $w->startsAt) {
                        return [$w]; // No overlap
                    }

                    $result = [];

                    if ($w->startsAt < $bookingStart) {
                        $result[] = new WindowDTO($w->assignmentId, $w->date, $w->startsAt, $bookingStart, $w->status);
                    }

                    $result[] = new WindowDTO($w->assignmentId, $w->date, $bookingStart, $bookingEnd, 'BOOKED');

                    if ($bookingEnd < $w->endsAt) {
                        $result[] = new WindowDTO($w->assignmentId, $w->date, $bookingEnd, $w->endsAt, $w->status);
                    }

                    return $result;
                });
            }

            return $remaining->all();
        });
    }
}
