<?php

namespace App\Domain\Availability\DTOs;

use App\Models\Provider;
use App\Models\Location;

/**
 * ProviderLocationContext
 *
 * The canonical unit of availability resolution in the destination architecture.
 *
 * Provider is the business/tenant.
 * Location is the optional physical operating context.
 *
 * Independent/mobile providers have location_id = null → schedules resolve to ProviderSchedule.
 * Branch providers have a non-null location_id → schedules resolve to LocationSchedule,
 * falling back to ProviderSchedule on INHERIT mode.
 *
 * This replaces salon_id as the primary argument to all availability,
 * scheduling, booking configuration, and resource resolution services.
 */
final readonly class ProviderLocationContext
{
    public function __construct(
        public string  $providerId,
        public ?string $locationId,
        public ?string $timezone = null,
    ) {}

    /**
     * Build directly from Provider + optional Location.
     */
    public static function fromProvider(Provider $provider, ?Location $location = null): self
    {
        return new self(
            providerId: $provider->id,
            locationId: $location?->id,
            timezone: $location?->timezone ?? $provider->timezone ?? null,
        );
    }

    /**
     * True for independent/mobile providers — no fixed location.
     */
    public function isIndependent(): bool
    {
        return $this->locationId === null;
    }

    /**
     * Stable cache segment — use in cache key construction.
     */
    public function cacheKey(): string
    {
        return $this->providerId . ':' . ($this->locationId ?? 'null');
    }
}
