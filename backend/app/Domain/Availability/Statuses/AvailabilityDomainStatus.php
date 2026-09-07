<?php

namespace App\Domain\Availability\Statuses;

/**
 * AvailabilityDomainStatus
 *
 * Internal domain status enum for the Availability subsystem.
 * These statuses represent the business facts about why a salon/branch/specialist
 * is or isn't available for booking. They are NEVER exposed directly to customers.
 *
 * Architecture:
 * - These are rich, granular internal states (17 distinct statuses)
 * - They map via AvailabilityStatusMapper to 4 simple public statuses
 * - Adding new internal states NEVER breaks the API (mapper handles translation)
 * - Used by AvailabilityEngine, CustomerAvailabilityService, and diagnostics
 *
 * Status Categories:
 * - Configuration: PROVIDER_NOT_CONFIGURED, BRANCH_NOT_CONFIGURED, etc.
 * - Operational: PROVIDER_CLOSED, BRANCH_CLOSED, ASSIGNMENT_OFF
 * - Temporary: ON_BREAK, ON_LEAVE, EXCEPTION_CLOSED
 * - Slot-level: SLOT_BOOKED, SLOT_LOCKED, NO_WINDOWS
 * - System: OUTSIDE_BOOKING_WINDOW, UNAVAILABLE
 * - Success: AVAILABLE
 *
 * @see AvailabilityStatusMapper for domain → public translation
 * @see AvailabilityPublicStatus for the 4 customer-facing statuses
 */
enum AvailabilityDomainStatus: string
{
    // Success state - booking is available
    case AVAILABLE = 'AVAILABLE';

    // Configuration required - entity not set up for online booking
    case PROVIDER_NOT_CONFIGURED = 'PROVIDER_NOT_CONFIGURED';
    case PROVIDER_DISABLED = 'PROVIDER_DISABLED';
    case SUBSCRIPTION_NOT_CONFIGURED = 'SUBSCRIPTION_NOT_CONFIGURED';
    case BRANCH_NOT_CONFIGURED = 'BRANCH_NOT_CONFIGURED';
    case ASSIGNMENT_NOT_CONFIGURED = 'ASSIGNMENT_NOT_CONFIGURED';
    case MAINTENANCE_NOT_CONFIGURED = 'MAINTENANCE_NOT_CONFIGURED';

    // Operational closures - entity is closed on this day
    case PROVIDER_CLOSED = 'PROVIDER_CLOSED';
    case BRANCH_CLOSED = 'BRANCH_CLOSED';
    case ASSIGNMENT_OFF = 'ASSIGNMENT_OFF';

    // Exception closures - holidays, events, etc.
    case EXCEPTION_CLOSED = 'EXCEPTION_CLOSED';

    // Temporary unavailability - staff-level
    case ON_BREAK = 'ON_BREAK';
    case ON_LEAVE = 'ON_LEAVE';

    // Slot-level unavailability
    case SLOT_BOOKED = 'SLOT_BOOKED';
    case SLOT_LOCKED = 'SLOT_LOCKED';

    // No available time windows
    case NO_WINDOWS = 'NO_WINDOWS';

    // Booking window constraints
    case OUTSIDE_BOOKING_WINDOW = 'OUTSIDE_BOOKING_WINDOW';

    // Generic fallback
    case UNAVAILABLE = 'UNAVAILABLE';

    /**
     * Returns priority rank for status conflict resolution.
     * Lower rank = higher priority (wins in conflicts).
     * Configuration issues (1-6) > Closures (7-10) > Temporary (11-14) > System (15-16) > Available (99).
     *
     * @return int Priority rank (1-99, lower is higher priority)
     */
    public function rank(): int
    {
        return match ($this) {
            self::PROVIDER_NOT_CONFIGURED        => 1,
            self::PROVIDER_DISABLED              => 2,
            self::SUBSCRIPTION_NOT_CONFIGURED    => 3,
            self::BRANCH_NOT_CONFIGURED          => 4,
            self::ASSIGNMENT_NOT_CONFIGURED      => 5,
            self::MAINTENANCE_NOT_CONFIGURED     => 6,
            self::EXCEPTION_CLOSED               => 7,
            self::PROVIDER_CLOSED                => 8,
            self::BRANCH_CLOSED                  => 9,
            self::ASSIGNMENT_OFF                 => 10,
            self::ON_LEAVE                       => 11,
            self::SLOT_LOCKED                    => 12,
            self::SLOT_BOOKED                    => 13,
            self::ON_BREAK                       => 14,
            self::NO_WINDOWS                     => 15,
            self::OUTSIDE_BOOKING_WINDOW         => 16,
            self::UNAVAILABLE                    => 17,
            self::AVAILABLE                      => 99,
        };
    }

    /**
     * Get rank of a status (accepts enum or string).
     * Returns 50 (medium priority) for unknown statuses.
     *
     * @param self|string $status Status to rank
     * @return int Priority rank
     */
    public static function rankOf(self|string $status): int
    {
        $enum = is_string($status) ? self::tryFrom($status) : $status;
        return $enum?->rank() ?? 50;
    }

    /**
     * Select the highest-priority status from an array.
     * Used when multiple constraints apply simultaneously (e.g., branch closed + specialist on leave).
     * The status with the lowest rank wins.
     *
     * @param array<int, self|string> $statuses Competing statuses
     * @param self $fallback Default if array is empty
     * @return self The winning status
     */
    public static function pickWinner(array $statuses, self $fallback): self
    {
        if (empty($statuses)) {
            return $fallback;
        }

        $winner = $fallback;
        $winnerRank = $fallback->rank();

        foreach ($statuses as $s) {
            $enum = $s instanceof self ? $s : self::tryFrom($s);
            if (!$enum) {
                continue;
            }
            $r = $enum->rank();
            if ($r < $winnerRank) {
                $winner     = $enum;
                $winnerRank = $r;
            }
        }

        return $winner;
    }
}
