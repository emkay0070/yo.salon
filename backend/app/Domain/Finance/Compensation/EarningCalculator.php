<?php

namespace App\Domain\Finance\Compensation;

use App\Models\Booking;

/**
 * EarningCalculator computes the gross earning amount for a payee from a booking.
 *
 * This is a pure calculation service — it performs NO database writes.
 * It returns the calculated amount and metadata; the caller decides what to persist.
 *
 * Calculation logic by policy type:
 *   commission  → rate × booking_amount, service-category-aware
 *   per_booking → flat amount per booking (from rules)
 *   salary      → NOT calculated here (salary earnings originate from CompensationPeriod)
 *   fixed       → NOT calculated here (same as salary)
 *   hybrid      → commission portion only (base is handled by PeriodClosingService)
 */
class EarningCalculator
{
    /**
     * Calculate the gross earning for a given policy and booking.
     *
     * @return array{amount: float, metadata: array}|null
     *   Returns null if the policy type produces no booking-triggered earning.
     */
    public function calculate(CompensationPolicy $policy, Booking $booking): ?array
    {
        return match ($policy->type) {
            'commission'  => $this->calculateCommission($policy, $booking),
            'per_booking' => $this->calculatePerBooking($policy, $booking),
            'hybrid'      => $this->calculateHybridCommission($policy, $booking),
            // salary, fixed — earnings sourced from period, not booking
            default       => null,
        };
    }

    // ── Private calculators ───────────────────────────────────────────────────

    private function calculateCommission(CompensationPolicy $policy, Booking $booking): ?array
    {
        $bookingAmount = $this->resolveBookingAmount($booking);

        if ($bookingAmount <= 0) {
            return null;
        }

        // Resolve service category from the booking (if available)
        $serviceCategory = $this->resolveServiceCategory($booking);
        $rule = $policy->ruleForCategory($serviceCategory);

        if (!$rule || !isset($rule['rate'])) {
            return null;
        }

        $rate   = (float) $rule['rate'];
        $amount = round($bookingAmount * $rate, 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'amount'   => $amount,
            'metadata' => [
                'type'             => 'commission',
                'booking_amount'   => $bookingAmount,
                'rate'             => $rate,
                'service_category' => $serviceCategory,
                'rule_applied'     => $rule,
            ],
        ];
    }

    private function calculatePerBooking(CompensationPolicy $policy, Booking $booking): ?array
    {
        $rule = $policy->ruleForCategory(null);

        if (!$rule || !isset($rule['amount'])) {
            return null;
        }

        $amount = (float) $rule['amount'];

        if ($amount <= 0) {
            return null;
        }

        return [
            'amount'   => $amount,
            'metadata' => [
                'type'         => 'per_booking',
                'flat_amount'  => $amount,
                'rule_applied' => $rule,
            ],
        ];
    }

    private function calculateHybridCommission(CompensationPolicy $policy, Booking $booking): ?array
    {
        // Only calculate the commission portion from the booking.
        // The base salary portion is calculated separately by PeriodClosingService.
        $rules = $policy->rules ?? [];
        $commissionRules = array_filter($rules, fn($r) => ($r['type'] ?? null) === 'commission');

        if (empty($commissionRules)) {
            return null;
        }

        $bookingAmount = $this->resolveBookingAmount($booking);

        if ($bookingAmount <= 0) {
            return null;
        }

        $serviceCategory = $this->resolveServiceCategory($booking);
        $totalAmount = 0;
        $appliedRules = [];

        foreach ($commissionRules as $rule) {
            $ruleCategory = $rule['service_category'] ?? '*';
            if ($ruleCategory !== '*' && $ruleCategory !== $serviceCategory) {
                continue;
            }

            $rate   = (float) ($rule['rate'] ?? 0);
            $amount = round($bookingAmount * $rate, 2);
            $totalAmount += $amount;
            $appliedRules[] = $rule;
            break; // First matching rule wins
        }

        if ($totalAmount <= 0) {
            return null;
        }

        return [
            'amount'   => $totalAmount,
            'metadata' => [
                'type'             => 'hybrid_commission',
                'booking_amount'   => $bookingAmount,
                'service_category' => $serviceCategory,
                'rules_applied'    => $appliedRules,
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveBookingAmount(Booking $booking): float
    {
        // Use amount_paid if available, otherwise amount_due.
        // This ensures we calculate commission on what was actually collected.
        return (float) ($booking->amount_paid ?: $booking->amount_due ?: 0);
    }

    private function resolveServiceCategory(Booking $booking): ?string
    {
        // Attempt to resolve service category from booking's service relationship
        // Falls back to null if the relationship is not loaded or unavailable.
        try {
            $service = $booking->service ?? $booking->services()->first() ?? null;
            if ($service) {
                return $service->category ?? $service->craft_taxonomy_id ?? null;
            }
        } catch (\Throwable) {
            // Gracefully handle any relationship resolution failure
        }

        return null;
    }
}
