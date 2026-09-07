<?php

namespace App\Domain\Availability\DTOs;

use App\Domain\Availability\Statuses\AvailabilityDomainStatus;
use Illuminate\Support\Collection;

/**
 * AvailabilityResult
 *
 * Complete availability response DTO returned by AvailabilityEngine.
 * This is the canonical data structure for availability queries.
 *
 * Architecture:
 * - Replaces magic-string array returns with typed, documented structure
 * - Contains domain status (internal) for mapper translation
 * - Includes all context needed for UI rendering and diagnostics
 * - Serializable via toLegacyPayload() for backward compatibility
 *
 * Properties:
 * - salonId, salonName, date, timezone: Context identifiers
 * - status: The primary AvailabilityDomainStatus (may be composite winner)
 * - allStatuses: All contributing statuses (for diagnostics)
 * - slots: Collection of available time slots
 * - schedule: ResolvedSchedule with hours/breaks
 * - operatingHours: Raw open/close times
 * - isClosed: Whether entity is closed on this date
 * - firstAvailableSlot, totalAvailableSlots: Slot summary
 *
 * @see AvailabilityEngine::getAvailableSlots() for creation
 * @see AvailabilityStatusMapper::toPublicResponse() for API translation
 */
final readonly class AvailabilityResult
{
    /**
     * @param  string  $salonId
     * @param  string  $date
     * @param  AvailabilityDomainStatus  $status
     * @param  array<int, AvailabilityDomainStatus>  $allStatuses
     * @param  Collection<int, array{start: string, end: string, duration: int, available_specialists?: list<array{id: string, name: string, skill_level: string, price: int|float}>, base_price?: int|float}>  $slots
     * @param  ResolvedSchedule|null  $schedule
     * @param  string|null  $salonName
     * @param  string|null  $timezone
     * @param  array{open: string|null, close: string|null}|null  $operatingHours
     * @param  bool  $isClosed
     * @param  array{start: string, end: string, duration: int}|null  $firstAvailableSlot
     * @param  int  $totalAvailableSlots
     */
    public function __construct(
        public string $salonId,
        public string $date,
        public AvailabilityDomainStatus $status,
        public array $allStatuses = [],
        public Collection $slots,
        public ?ResolvedSchedule $schedule = null,
        public ?string $salonName = null,
        public ?string $timezone = null,
        public ?array $operatingHours = null,
        public bool $isClosed = false,
        public ?array $firstAvailableSlot = null,
        public int $totalAvailableSlots = 0,
    ) {}

    /**
     * Convert to legacy array format for backward compatibility.
     * Used by AvailabilityStatusMapper's inferDomainStatus() heuristic
     * and by consumers not yet migrated to the typed DTO.
     *
     * @return array<string, mixed> Legacy-shaped payload
     */
    public function toLegacyPayload(): array
    {
        return [
            'salon_id'              => $this->salonId,
            'salon_name'            => $this->salonName,
            'date'                  => $this->date,
            'timezone'              => $this->timezone,
            'operating_hours'       => $this->operatingHours,
            'is_closed'             => $this->isClosed,
            'slots'                 => $this->slots->all(),
            'first_available_slot'  => $this->firstAvailableSlot,
            'total_available_slots' => $this->totalAvailableSlots,
            'domain_status'         => $this->status->value,
            'domain_statuses'       => array_map(
                static fn (AvailabilityDomainStatus $s) => $s->value,
                $this->allStatuses,
            ),
        ];
    }
}
