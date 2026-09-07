<?php

namespace App\Domain\Availability\DTOs;

use Carbon\CarbonImmutable;

/**
 * WindowDTO
 *
 * Represents a single computed availability window.
 * An engine generates these; a booking engine later
 * splits them into bookable appointment slots by service duration.
 */
class WindowDTO
{
    public function __construct(
        public readonly string        $assignmentId,
        public readonly CarbonImmutable $date,
        public readonly string        $startsAt,   // HH:MM:SS
        public readonly string        $endsAt,     // HH:MM:SS
        public readonly string        $status = 'AVAILABLE', // AVAILABLE, BLOCKED, BREAK, LEAVE
    ) {}
}
