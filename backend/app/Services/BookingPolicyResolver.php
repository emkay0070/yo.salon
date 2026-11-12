<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;

/**
 * BookingPolicyResolver
 */
class BookingPolicyResolver
{
    protected ?Service $resolvedService = null;
    protected array $availabilityPolicy;
    protected array $paymentPolicy;

    public function __construct(
        protected Provider   $provider,
        protected Location   $location,
        protected Service    $service,
        protected ?Specialist $specialist = null,
    ) {
        $catalogResolver = new LocationServiceCatalogResolver();

        // Pre-load the service with the leftJoined pivot aliases for this location
        $this->resolvedService = $catalogResolver->queryAllServices($location)
            ->where('services.id', $service->id)
            ->first();

        $bookingConfig = $provider->bookingConfig;
        $this->availabilityPolicy = $bookingConfig ? $bookingConfig->getDefaultAvailabilityPolicy() : [];
        $this->paymentPolicy = $bookingConfig ? $bookingConfig->getDefaultPaymentPolicy() : [];
    }

    // ── Price ─────────────────────────────────────────────────────────────────

    public function effectivePrice(): float
    {
        return $this->resolvedService?->pivot_price_override
            ?? $this->service->price;
    }

    // ── Duration ──────────────────────────────────────────────────────────────

    public function effectiveDuration(): int
    {
        return $this->resolvedService?->pivot_duration_override
            ?? $this->service->duration;
    }

    public function bufferBefore(): int
    {
        return $this->resolvedService?->pivot_buffer_before_override
            ?? $this->service->buffer_before
            ?? 0;
    }

    public function bufferAfter(): int
    {
        return $this->resolvedService?->pivot_buffer_after_override
            ?? $this->service->buffer_after
            ?? 0;
    }

    // ── Booking Rules ─────────────────────────────────────────────────────────

    public function minBookingNoticeHours(): int
    {
        return $this->resolvedService?->pivot_min_booking_notice_override
            ?? $this->service->min_booking_notice
            ?? $this->availabilityPolicy['min_notice_hours'] ?? 0;
    }

    public function cancellationCutoffHours(): int
    {
        return $this->resolvedService?->pivot_cancellation_cutoff_override
            ?? $this->service->cancellation_cutoff_hours
            ?? 0;
    }

    // ── Availability Gates ────────────────────────────────────────────────────

    public function isAvailableAtBranch(): bool
    {
        if (!$this->resolvedService || $this->resolvedService->pivot_is_available === null) {
            return true;
        }
        return (bool) $this->resolvedService->pivot_is_available;
    }

    public function acceptsOnlineBooking(): bool
    {
        // Provider level check (e.g., if there's a global toggle, but for now default to true)
        if ($this->resolvedService && $this->resolvedService->pivot_online_booking_enabled === 0) {
            return false;
        }
        return true;
    }

    // ── Deposit ───────────────────────────────────────────────────────────────

    public function requiresDeposit(): bool
    {
        if ($this->resolvedService && !is_null($this->resolvedService->pivot_deposit_required_override)) {
            return (bool) $this->resolvedService->pivot_deposit_required_override;
        }

        $strategy = $this->paymentPolicy['strategy'] ?? 'no_payment';
        if ($strategy !== 'deposit') {
            return false;
        }

        return true;
    }

    public function depositAmount(): float
    {
        if (!$this->requiresDeposit()) {
            return 0;
        }

        $price = $this->effectivePrice();
        $depositType = $this->paymentPolicy['deposit']['type'] ?? 'percentage';
        $depositValue = $this->paymentPolicy['deposit']['amount'] ?? 0;

        return match ($depositType) {
            'percentage' => round($price * ($depositValue / 100), 2),
            'fixed'      => (float) $depositValue,
            default      => 0,
        };
    }

    // ── Summary ───────────────────────────────────────────────────────────────

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
