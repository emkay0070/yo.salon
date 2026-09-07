<?php

namespace App\Domain\Catalog\Engines;

use App\Models\Salon;
use App\Models\Service;
use App\Domain\Availability\Engines\AvailabilityEngine;
use Carbon\CarbonImmutable;

/**
 * BookabilityEngine
 * 
 * Computes booking rules (duration, buffers, availability, notices)
 * for a specific service at a specific branch, and validates if a 
 * given time slot satisfies all bookability criteria.
 */
class BookabilityEngine
{
    public function __construct(
        protected Salon $salon,
        protected Service $resolvedService
    ) {}

    /**
     * Effective duration in minutes.
     * Resolution: Salon override → Provider (service) duration
     */
    public function effectiveDuration(): int
    {
        return $this->resolvedService->pivot_duration_override
            ?? $this->resolvedService->duration;
    }

    /**
     * Buffer minutes before appointment.
     * Resolution: Salon override → Provider (service) buffer_before
     */
    public function bufferBefore(): int
    {
        return $this->resolvedService->pivot_buffer_before_override
            ?? $this->resolvedService->buffer_before
            ?? 0;
    }

    /**
     * Buffer minutes after appointment.
     * Resolution: Salon override → Provider (service) buffer_after
     */
    public function bufferAfter(): int
    {
        return $this->resolvedService->pivot_buffer_after_override
            ?? $this->resolvedService->buffer_after
            ?? 0;
    }

    /**
     * Minimum notice in hours before a booking can be made.
     * Resolution: Salon override → Service setting → 0
     */
    public function minBookingNoticeHours(): int
    {
        return $this->resolvedService->pivot_min_booking_notice_override
            ?? $this->resolvedService->min_booking_notice
            ?? 0;
    }

    /**
     * Hours before appointment within which cancellation is blocked.
     * Resolution: Salon override → Service setting → 0
     */
    public function cancellationCutoffHours(): int
    {
        return $this->resolvedService->pivot_cancellation_cutoff_override
            ?? $this->resolvedService->cancellation_cutoff_hours
            ?? 0;
    }

    /**
     * Is this service available at this branch at all?
     * Open-world assumption: true unless explicitly set to false (0).
     */
    public function isAvailableAtBranch(): bool
    {
        if ($this->resolvedService->pivot_is_available === null) {
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
        if ($this->resolvedService->pivot_online_booking_enabled === 0) {
            return false;
        }
        return true;
    }

    /**
     * Validates if a requested slot is fully bookable.
     * Combines AvailabilityEngine check with Bookability rules (buffers, lead time).
     */
    public function isSlotBookable(
        AvailabilityEngine $availabilityEngine,
        string $assignmentId,
        string $date,
        string $startTime
    ): array {
        // 1. Check lead time (min booking notice)
        $requestedStart = CarbonImmutable::parse("$date $startTime");
        $minNoticeHours = $this->minBookingNoticeHours();
        
        if ($minNoticeHours > 0) {
            $earliestAllowed = CarbonImmutable::now()->addHours($minNoticeHours);
            if ($requestedStart->lessThan($earliestAllowed)) {
                return [
                    'bookable' => false,
                    'reason' => "This service requires at least {$minNoticeHours} hours notice."
                ];
            }
        }

        // 2. Calculate total required duration (duration + buffers)
        $duration = $this->effectiveDuration();
        $bufferBefore = $this->bufferBefore();
        $bufferAfter = $this->bufferAfter();
        
        $totalDurationRequired = $bufferBefore + $duration + $bufferAfter;

        // 3. Check actual availability
        // Note: The requested slot passed to AvailabilityEngine must include the buffer before.
        $actualStartTime = $requestedStart->subMinutes($bufferBefore)->format('H:i:s');

        $isAvailable = $availabilityEngine->isSlotAvailable(
            $assignmentId,
            $date,
            $actualStartTime,
            $totalDurationRequired
        );

        if (!$isAvailable) {
            return [
                'bookable' => false,
                'reason' => "The specialist is not available for the required duration."
            ];
        }

        return [
            'bookable' => true,
            'reason' => null
        ];
    }
}
