<?php

namespace App\Domain\Availability\Engines;

use App\Domain\Availability\Queries\AvailabilityContextBuilder;
use App\Domain\Availability\Engines\AvailabilityWindowGenerator;
use Carbon\CarbonImmutable;

/**
 * AvailabilityEngine
 * 
 * Exposes a public interface to the Availability domain.
 * Evaluates whether a specialist has an available slot for a given duration.
 */
class AvailabilityEngine
{
    private AvailabilityContextBuilder $contextQuery;
    private AvailabilityWindowGenerator $generator;

    public function __construct(
        AvailabilityContextBuilder $contextQuery,
        AvailabilityWindowGenerator $generator
    ) {
        $this->contextQuery = $contextQuery;
        $this->generator = $generator;
    }

    /**
     * Check if a specific time slot is available for a specialist assignment.
     */
    public function isSlotAvailable(string $assignmentId, string $date, string $startTime, int $durationMinutes): bool
    {
        $targetDate = CarbonImmutable::parse($date);
        $context = $this->contextQuery->build($assignmentId, $targetDate);

        // Generate the available slots for this date
        $availableSlots = $this->generator->generate($context);

        $requestedStart = CarbonImmutable::parse("$date $startTime");
        $requestedEnd = $requestedStart->addMinutes($durationMinutes);

        // Check if the requested slot fits entirely within any of the available slots
        foreach ($availableSlots as $slot) {
            $slotStart = CarbonImmutable::parse("$date {$slot->startsAt}");
            $slotEnd = CarbonImmutable::parse("$date {$slot->endsAt}");

            if ($requestedStart->greaterThanOrEqualTo($slotStart) && $requestedEnd->lessThanOrEqualTo($slotEnd)) {
                return true;
            }
        }

        return false;
    }
}
