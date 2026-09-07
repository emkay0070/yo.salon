<?php

namespace App\Domain\Finance\Compensation;

use App\Models\Booking;

/**
 * CompensationPolicyResolver finds the correct active CompensationPolicy
 * for a given payee in the context of a specific booking or salon.
 *
 * Resolution priority:
 *   1. Assignment-scoped policy (specialist working at a specific salon)
 *   2. Compensatable + provider policy (non-specialist staff, or fallback)
 *   3. null — no policy, nothing to earn
 *
 * The resolver picks the policy active AT THE TIME of the booking date,
 * not at the time of calculation. This ensures policy changes don't
 * retroactively alter past earnings.
 */
class CompensationPolicyResolver
{
    /**
     * Resolve the CompensationPolicy for a payee from a completed booking.
     *
     * @param string $compensatableType  e.g. App\Models\Specialist
     * @param string $compensatableId    UUID of the payee
     * @param string $providerType       e.g. App\Models\Salon
     * @param string $providerId         UUID of the salon
     * @param string|null $assignmentId  If the payee is a specialist with an assignment
     * @param \DateTimeInterface|null $asOf  Date to evaluate policy against (defaults to now)
     */
    public function resolve(
        string $compensatableType,
        string $compensatableId,
        string $providerType,
        string $providerId,
        ?string $assignmentId = null,
        ?\DateTimeInterface $asOf = null
    ): ?CompensationPolicy {
        $asOf ??= now();
        $date = $asOf instanceof \Carbon\Carbon ? $asOf->toDateString() : $asOf->format('Y-m-d');

        // Priority 1: assignment-scoped (specialist with a specific salon assignment)
        if ($assignmentId) {
            $policy = CompensationPolicy::forAssignment($assignmentId)
                ->forCompensatable($compensatableType, $compensatableId)
                ->where('is_active', true)
                ->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('effective_until')
                      ->orWhere('effective_until', '>=', $date);
                })
                ->orderByDesc('effective_from')
                ->first();

            if ($policy) {
                return $policy;
            }
        }

        // Priority 2: compensatable + provider (non-specialist or fallback)
        return CompensationPolicy::forCompensatable($compensatableType, $compensatableId)
            ->forProvider($providerType, $providerId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Resolve directly from a Booking.
     * Picks the policy active on the booking's date, not today.
     */
    public function resolveForBooking(
        string $compensatableType,
        string $compensatableId,
        Booking $booking
    ): ?CompensationPolicy {
        return $this->resolve(
            compensatableType: $compensatableType,
            compensatableId:   $compensatableId,
            providerType:      \App\Models\Salon::class,
            providerId:        $booking->salon_id,
            assignmentId:      null, // TODO: resolve assignment_id from booking when available
            asOf:              $booking->date instanceof \DateTimeInterface
                                   ? $booking->date
                                   : \Carbon\Carbon::parse($booking->date),
        );
    }
}
