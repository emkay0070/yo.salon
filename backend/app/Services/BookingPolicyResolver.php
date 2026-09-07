<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Specialist;

/**
 * BookingPolicyResolver
 *
 * Resolves the effective booking policy for any combination of
 * Salon + Service + Specialist by walking the resolution chain:
 *
 *   Provider defaults
 *       ↓
 *   Salon overrides     (salon_service pivot, salon columns)
 *       ↓
 *   Service overrides   (service columns)
 *       ↓
 *   Specialist overrides (future: specialist_service pivot)
 *       ↓
 *   Booking-specific    (applied at booking creation time)
 *
 * The first non-null value found walking down the chain wins.
 * This means lower levels can always override higher levels.
 *
 * Usage:
 *   $resolver = new BookingPolicyResolver($salon, $service, $specialist);
 *   $price    = $resolver->effectivePrice();
 *   $duration = $resolver->effectiveDuration();
 *   $canBook  = $resolver->acceptsOnlineBooking();
 */
class BookingPolicyResolver
{
    protected ?Service $resolvedService = null;

    public function __construct(
        protected Salon      $salon,
        protected Service    $service,
        protected ?Specialist $specialist = null,
    ) {
        $catalogResolver = new BranchServiceCatalogResolver();
        
        // Pre-load the service with the leftJoined pivot aliases for this branch
        $this->resolvedService = $catalogResolver->queryAllServices($salon)
            ->where('services.id', $service->id)
            ->first();
    }

    // ── Price ─────────────────────────────────────────────────────────────────

    /**
     * Effective price for a booking at this salon.
     * Resolution: Salon override → Provider (service) price
     */
    public function effectivePrice(): float
    {
        return $this->resolvedService?->pivot_price_override
            ?? $this->service->price;
    }

    // ── Duration ──────────────────────────────────────────────────────────────

    /**
     * Effective duration in minutes.
     * Resolution: Salon override → Provider (service) duration
     */
    public function effectiveDuration(): int
    {
        return $this->resolvedService?->pivot_duration_override
            ?? $this->service->duration;
    }

    /**
     * Buffer minutes before appointment.
     * Resolution: Salon override → Provider (service) buffer_before
     */
    public function bufferBefore(): int
    {
        return $this->resolvedService?->pivot_buffer_before_override
            ?? $this->service->buffer_before
            ?? 0;
    }

    /**
     * Buffer minutes after appointment.
     * Resolution: Salon override → Provider (service) buffer_after
     */
    public function bufferAfter(): int
    {
        return $this->resolvedService?->pivot_buffer_after_override
            ?? $this->service->buffer_after
            ?? 0;
    }

    // ── Booking Rules ─────────────────────────────────────────────────────────

    /**
     * Minimum notice in hours before a booking can be made.
     * Resolution: Salon override → Service setting → Salon default → 0
     */
    public function minBookingNoticeHours(): int
    {
        return $this->resolvedService?->pivot_min_booking_notice_override
            ?? $this->service->min_booking_notice
            ?? 0;
    }

    /**
     * Hours before appointment within which cancellation is blocked.
     * Resolution: Salon override → Service setting → 0
     */
    public function cancellationCutoffHours(): int
    {
        return $this->resolvedService?->pivot_cancellation_cutoff_override
            ?? $this->service->cancellation_cutoff_hours
            ?? 0;
    }

    // ── Availability Gates ────────────────────────────────────────────────────

    /**
     * Is this service available at this branch at all?
     *
     * Open-world assumption: if no salon_service record exists,
     * the service is inherited from the provider catalog and is available.
     * Only an explicit is_available = false blocks it.
     */
    public function isAvailableAtBranch(): bool
    {
        // No pivot record or is_available is explicitly true or null
        if (!$this->resolvedService || $this->resolvedService->pivot_is_available === null) {
            return true;
        }
        return (bool) $this->resolvedService->pivot_is_available;
    }

    /**
     * Can a customer book this service online at this branch?
     * Resolution: Branch online flag → Salon-level online flag → true
     */
    public function acceptsOnlineBooking(): bool
    {
        if (!$this->salon->accepting_online_bookings) {
            return false;
        }
        if ($this->resolvedService && $this->resolvedService->pivot_online_booking_enabled === 0) {
            return false;
        }
        return true;
    }

    // ── Deposit ───────────────────────────────────────────────────────────────

    /**
     * Is a deposit required for this booking?
     * Resolution: Salon-service override → Salon booking deposit policy
     */
    public function requiresDeposit(): bool
    {
        // Explicit override on the pivot takes precedence
        if ($this->resolvedService && !is_null($this->resolvedService->pivot_deposit_required_override)) {
            return (bool) $this->resolvedService->pivot_deposit_required_override;
        }

        // Fall back to salon booking deposit policy
        if (!$this->salon->booking_deposit_enabled) {
            return false;
        }

        return match ($this->salon->deposit_required_for) {
            'all'     => true,
            'above'   => $this->effectivePrice() >= ($this->salon->deposit_min_service_amount ?? 0),
            default   => false,
        };
    }

    /**
     * Deposit amount in currency units (not percentage).
     */
    public function depositAmount(): float
    {
        if (!$this->requiresDeposit()) {
            return 0;
        }

        $price = $this->effectivePrice();

        return match ($this->salon->deposit_type) {
            'percentage' => round($price * ($this->salon->deposit_value / 100), 2),
            'fixed'      => (float) ($this->salon->deposit_value ?? 0),
            default      => 0,
        };
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /**
     * Returns the full resolved policy as an array.
     * Use this when serializing for the API or booking confirmation.
     */
    public function toArray(): array
    {
        return [
            'is_available'         => $this->isAvailableAtBranch(),
            'accepts_online'       => $this->acceptsOnlineBooking(),
            'price'                => $this->effectivePrice(),
            'duration'             => $this->effectiveDuration(),
            'buffer_before'        => $this->bufferBefore(),
            'buffer_after'         => $this->bufferAfter(),
            'min_booking_notice'   => $this->minBookingNoticeHours(),
            'cancellation_cutoff'  => $this->cancellationCutoffHours(),
            'requires_deposit'     => $this->requiresDeposit(),
            'deposit_amount'       => $this->depositAmount(),
        ];
    }
}
