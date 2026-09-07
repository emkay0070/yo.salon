<?php

namespace App\Domain\Availability\Statuses;

/**
 * AvailabilityStatusResult
 *
 * Lightweight result object containing both public and internal status information.
 * Used when you need to preserve the domain reason while exposing a public status.
 *
 * Properties:
 * - status: The customer-facing status (4 possible values)
 * - reason: The internal domain status (17 possible values) for diagnostics/analytics
 *
 * Use Cases:
 * - Dashboard diagnostics (show internal reason to owners)
 * - Analytics (track granular unavailability reasons)
 * - Support troubleshooting (know exactly why booking failed)
 * - AI features (rich context for recommendations)
 *
 * @see AvailabilityStatusMapper::map() for creation
 */
final readonly class AvailabilityStatusResult
{
    public function __construct(
        public AvailabilityPublicStatus $status,
        public AvailabilityDomainStatus $reason,
    ) {}

    /**
     * Serialize to array for API responses.
     * The `status` field is customer-facing (lowercase).
     * The `reason` field is internal (lowercase) for diagnostics.
     *
     * @return array{status: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => strtolower($this->reason->value),
        ];
    }
}
