<?php

namespace App\Domain\Availability\Statuses;

use App\Domain\Availability\DTOs\AvailabilityResult;

/**
 * AvailabilityStatusMapper
 *
 * The ONE AND ONLY place that translates AvailabilityDomainStatus (internal,
 * rich, many) to AvailabilityPublicStatus (public, stable, tiny).
 *
 *                     domain complexity (internal)
 *                                │
 *                                ▼
 *                  AvailabilityStatusMapper (this class)
 *                                │
 *                                ▼
 *                 simple public API states (4 values)
 *
 * Usage:
 *
 *     $domain  = $engine->getStatus(...);   // AvailabilityDomainStatus
 *     $mapper->map($domain);                // AvailabilityStatusResult
 */
final class AvailabilityStatusMapper
{
    /**
     * Canonical mapping table. When adding a new internal domain state, add
     * it HERE — nowhere else.
     *
     * @var array<string, AvailabilityPublicStatus>
     */
    private const MAP = [
        'PROVIDER_NOT_CONFIGURED'      => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
        'PROVIDER_DISABLED'            => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
        'SUBSCRIPTION_NOT_CONFIGURED'  => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
        'BRANCH_NOT_CONFIGURED'        => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
        'ASSIGNMENT_NOT_CONFIGURED'    => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
        'MAINTENANCE_NOT_CONFIGURED'   => AvailabilityPublicStatus::CONFIGURATION_REQUIRED,

        'PROVIDER_CLOSED'              => AvailabilityPublicStatus::CLOSED,
        'BRANCH_CLOSED'                => AvailabilityPublicStatus::CLOSED,
        'ASSIGNMENT_OFF'               => AvailabilityPublicStatus::CLOSED,
        'EXCEPTION_CLOSED'             => AvailabilityPublicStatus::CLOSED,

        'ON_BREAK'                     => AvailabilityPublicStatus::UNAVAILABLE,
        'ON_LEAVE'                     => AvailabilityPublicStatus::UNAVAILABLE,
        'SLOT_BOOKED'                  => AvailabilityPublicStatus::UNAVAILABLE,
        'SLOT_LOCKED'                  => AvailabilityPublicStatus::UNAVAILABLE,
        'NO_WINDOWS'                   => AvailabilityPublicStatus::UNAVAILABLE,
        'OUTSIDE_BOOKING_WINDOW'       => AvailabilityPublicStatus::UNAVAILABLE,
        'UNAVAILABLE'                  => AvailabilityPublicStatus::UNAVAILABLE,

        'AVAILABLE'                    => AvailabilityPublicStatus::AVAILABLE,
    ];

    /**
     * Owner-visible (internal) human-readable reasons for each domain state.
     * Dashboard + Business Health diagnostics only; never customer-facing.
     *
     * @var array<string, string|null>
     */
    private const OWNER_REASONS = [
        'PROVIDER_NOT_CONFIGURED'      => 'Provider hours not configured.',
        'PROVIDER_DISABLED'            => 'Provider account is disabled.',
        'SUBSCRIPTION_NOT_CONFIGURED'  => 'Subscription expired or not provisioned.',
        'BRANCH_NOT_CONFIGURED'        => 'Branch schedule missing or still in Draft.',
        'ASSIGNMENT_NOT_CONFIGURED'    => 'Specialist schedule missing or still in Draft.',
        'MAINTENANCE_NOT_CONFIGURED'   => 'Online booking is under maintenance.',
        'PROVIDER_CLOSED'              => 'Provider is closed on this day.',
        'BRANCH_CLOSED'                => 'Branch is closed on this day.',
        'ASSIGNMENT_OFF'               => 'Specialist is not scheduled this day.',
        'EXCEPTION_CLOSED'             => 'Schedule exception (holiday / closure) applies.',
        'ON_BREAK'                     => 'Specialist is on a scheduled break.',
        'ON_LEAVE'                     => 'Specialist has approved time-off / unavailability.',
        'SLOT_BOOKED'                  => 'This slot has already been booked.',
        'SLOT_LOCKED'                  => 'This slot is temporarily held by another booking.',
        'NO_WINDOWS'                   => 'No bookable windows remain (fully booked).',
        'OUTSIDE_BOOKING_WINDOW'       => 'Booking date falls outside the allowed booking window.',
        'UNAVAILABLE'                  => 'Online booking is currently unavailable.',
        'AVAILABLE'                    => null,
    ];

    /**
     * Customer-visible messages mapped from public status.
     * Intentionally bland — diagnostic detail stays server-side.
     *
     * @var array<string, string|null>
     */
    private const PUBLIC_MESSAGES = [
        'AVAILABLE'              => null,
        'CLOSED'                 => 'No more appointments are available today.',
        'CONFIGURATION_REQUIRED' => "Online booking isn't currently available.",
        'UNAVAILABLE'            => "Online booking isn't currently available.",
    ];

    /**
     * Map a domain status to the public result object.
     */
    public function map(AvailabilityDomainStatus $domainStatus): AvailabilityStatusResult
    {
        $public = self::MAP[$domainStatus->value] ?? AvailabilityPublicStatus::UNAVAILABLE;

        return new AvailabilityStatusResult($public, $domainStatus);
    }

    /**
     * Map a single domain status to its public equivalent (string form).
     */
    public function toPublicStatus(AvailabilityDomainStatus|string $domainStatus): AvailabilityPublicStatus
    {
        $enum = $domainStatus instanceof AvailabilityDomainStatus
            ? $domainStatus
            : AvailabilityDomainStatus::tryFrom($domainStatus);

        if ($enum === null) {
            return AvailabilityPublicStatus::UNAVAILABLE;
        }

        return self::MAP[$enum->value] ?? AvailabilityPublicStatus::UNAVAILABLE;
    }

    /**
     * Owner-facing diagnostic reason (null when everything is fine).
     */
    public function ownerReason(AvailabilityDomainStatus|string $domainStatus): ?string
    {
        $enum = $domainStatus instanceof AvailabilityDomainStatus
            ? $domainStatus
            : AvailabilityDomainStatus::tryFrom($domainStatus);

        if ($enum === null) {
            return null;
        }

        return self::OWNER_REASONS[$enum->value] ?? null;
    }

    /**
     * Customer-facing message. Always null when AVAILABLE.
     */
    public function publicMessage(AvailabilityPublicStatus|string $publicStatus): ?string
    {
        $enum = $publicStatus instanceof AvailabilityPublicStatus
            ? $publicStatus
            : AvailabilityPublicStatus::tryFrom($publicStatus);

        if ($enum === null) {
            return null;
        }

        return self::PUBLIC_MESSAGES[$enum->value] ?? null;
    }

    /**
     * Transform a typed AvailabilityResult into the canonical public
     * response array. This is the controller's single exit point.
     *
     * @return array<string, mixed>
     */
    public function toPublicResponse(AvailabilityResult|array $payload): array
    {
        if ($payload instanceof AvailabilityResult) {
            $data = $payload->toLegacyPayload();
        } else {
            $data = $payload;
        }

        $domainStatus = isset($data['domain_status'])
            ? ($data['domain_status'] instanceof AvailabilityDomainStatus
                ? $data['domain_status']
                : (AvailabilityDomainStatus::tryFrom($data['domain_status']) ?? $this->inferDomainStatus($data)))
            : $this->inferDomainStatus($data);

        $mapped    = $this->map($domainStatus);
        $public    = $mapped->status;
        $message   = $this->publicMessage($public);

        $isClosed = match ($public) {
            AvailabilityPublicStatus::CLOSED => true,
            AvailabilityPublicStatus::CONFIGURATION_REQUIRED,
            AvailabilityPublicStatus::UNAVAILABLE => false,
            default => (bool) ($data['is_closed'] ?? false),
        };

        $data['domain_status']   = $domainStatus->value;
        $data['status']          = $public->value;
        $data['reason']          = strtolower($domainStatus->value);
        $data['is_closed']       = $isClosed;
        $data['message']         = $message ?? ($data['message'] ?? null);
        $data['internal_reason'] = $this->ownerReason($domainStatus);

        unset($data['reason_legacy']);

        return $data;
    }

    /**
     * Backward-compatible heuristic: when a payload predates the mapper
     * (no domain_status present), infer one from its shape.
     */
    private function inferDomainStatus(array $payload): AvailabilityDomainStatus
    {
        $hasSlots = isset($payload['slots'])
            && is_countable($payload['slots'])
            && count($payload['slots']) > 0;

        if ($hasSlots) {
            return AvailabilityDomainStatus::AVAILABLE;
        }

        if (($payload['is_closed'] ?? false) === true) {
            return AvailabilityDomainStatus::BRANCH_CLOSED;
        }

        $legacy = (string) ($payload['status'] ?? $payload['reason'] ?? '');

        return match (true) {
            str_contains($legacy, 'Specialist not assigned')
                => AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED,
            str_contains($legacy, 'closed')
                => AvailabilityDomainStatus::BRANCH_CLOSED,
            default
                => AvailabilityDomainStatus::NO_WINDOWS,
        };
    }
}
