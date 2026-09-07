<?php

namespace App\Domain\Availability\Statuses;

/**
 * AvailabilityPublicStatus
 *
 * Customer-facing status enum for the Availability API.
 * These are the ONLY statuses exposed to customers - simple, stable, and intentionally vague.
 *
 * Architecture:
 * - 4 stable public statuses (vs 17 internal domain statuses)
 * - Mapped from AvailabilityDomainStatus via AvailabilityStatusMapper
 * - Adding new internal states NEVER changes these 4 values
 * - Frontend depends only on these values (API contract)
 *
 * Status Meanings:
 * - available: Slots exist and can be booked
 * - closed: Entity is closed (no slots available today)
 * - configuration_required: Setup needed (no diagnostic detail exposed)
 * - unavailable: Temporary issue (break, leave, booked, etc.)
 *
 * @see AvailabilityDomainStatus for internal business facts
 * @see AvailabilityStatusMapper for translation logic
 */
enum AvailabilityPublicStatus: string
{
    case AVAILABLE              = 'available';
    case CLOSED                 = 'closed';
    case CONFIGURATION_REQUIRED = 'configuration_required';
    case UNAVAILABLE            = 'unavailable';
}
