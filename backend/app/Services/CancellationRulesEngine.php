<?php

namespace App\Services;

use Carbon\Carbon;

class CancellationRulesEngine
{
    private BookingConfigurationService $configService;

    public function __construct(BookingConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Check if cancellation is allowed
     */
    public function canCancel(string $serviceId, string $salonId, Carbon $bookingTime): array
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        $freeCancellationHours = $cancellationPolicy['free_cancellation_hours'] ?? 24;

        $now = Carbon::now();
        $hoursUntilBooking = $now->diffInHours($bookingTime, false);

        if ($hoursUntilBooking < 0) {
            return [
                'allowed' => false,
                'reason' => 'Booking time has passed',
                'hours_until_booking' => $hoursUntilBooking,
            ];
        }

        if ($hoursUntilBooking >= $freeCancellationHours) {
            return [
                'allowed' => true,
                'reason' => 'Within free cancellation period',
                'hours_until_booking' => $hoursUntilBooking,
                'free_cancellation' => true,
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'Past free cancellation period, cancellation allowed with fee',
            'hours_until_booking' => $hoursUntilBooking,
            'free_cancellation' => false,
        ];
    }

    /**
     * Calculate cancellation fee
     */
    public function calculateCancellationFee(string $serviceId, string $salonId, float $depositAmount, Carbon $bookingTime): array
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        $freeCancellationHours = $cancellationPolicy['free_cancellation_hours'] ?? 24;
        $lateFeeType = $cancellationPolicy['late_cancellation_fee'] ?? 'deposit';
        $lateFeeAmount = $cancellationPolicy['late_cancellation_amount'] ?? null;

        $now = Carbon::now();
        $hoursUntilBooking = $now->diffInHours($bookingTime, false);

        // Within free cancellation period
        if ($hoursUntilBooking >= $freeCancellationHours) {
            return [
                'fee' => 0,
                'fee_type' => 'none',
                'refundable_amount' => $depositAmount,
                'reason' => 'Free cancellation',
            ];
        }

        // Calculate late fee
        $fee = 0;

        switch ($lateFeeType) {
            case 'deposit':
                $fee = $depositAmount;
                break;
            case 'percentage':
                $percentage = $lateFeeAmount ?? 50;
                $fee = $depositAmount * ($percentage / 100);
                break;
            case 'fixed':
                $fee = $lateFeeAmount ?? 0;
                break;
            case 'none':
                $fee = 0;
                break;
        }

        return [
            'fee' => $fee,
            'fee_type' => $lateFeeType,
            'refundable_amount' => max(0, $depositAmount - $fee),
            'reason' => 'Late cancellation fee applied',
        ];
    }

    /**
     * Check if customer has no-show violations
     */
    public function checkNoShowStatus(string $customerId, string $salonId): array
    {
        $cancellationPolicy = $this->configService->getPolicy(null, $salonId, 'cancellation_policy');
        $noShowPolicy = $cancellationPolicy['no_show_policy'] ?? [];

        // Count no-shows in the last 90 days
        $noShowCount = \App\Models\Booking::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('status', 'no_show')
            ->where('date', '>=', Carbon::now()->subDays(90))
            ->count();

        $status = 'good';
        $restriction = null;

        if ($noShowCount >= 3) {
            $status = 'blocked';
            $restriction = $noShowPolicy['third_offense'] ?? 'full_payment_required';
        } elseif ($noShowCount >= 2) {
            $status = 'restricted';
            $restriction = $noShowPolicy['second_offense'] ?? 'deposit_required';
        } elseif ($noShowCount >= 1) {
            $status = 'warning';
            $restriction = $noShowPolicy['first_offense'] ?? 'warning';
        }

        return [
            'status' => $status,
            'no_show_count' => $noShowCount,
            'restriction' => $restriction,
            'message' => $this->getNoShowMessage($status, $restriction),
        ];
    }

    /**
     * Get no-show message for customer
     */
    protected function getNoShowMessage(string $status, ?string $restriction): string
    {
        switch ($status) {
            case 'blocked':
                return 'Due to multiple no-shows, full prepayment is required for future bookings.';
            case 'restricted':
                return 'Due to previous no-shows, a deposit is required for future bookings.';
            case 'warning':
                return 'Please note that additional no-shows may result in booking restrictions.';
            default:
                return 'No restrictions.';
        }
    }

    /**
     * Record a no-show for a customer
     */
    public function recordNoShow(string $bookingId): void
    {
        $booking = \App\Models\Booking::find($bookingId);
        if (!$booking) {
            return;
        }

        $booking->update([
            'status' => 'no_show',
        ]);

        // TODO: Send notification to customer
        // TODO: Apply any automatic restrictions
    }

    /**
     * Get late arrival grace period in minutes
     */
    public function getLateArrivalGracePeriod(string $serviceId, string $salonId): int
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        // Note: This would be in a separate late_arrival_policy, using cancellation for now
        return $cancellationPolicy['late_arrival_grace_minutes'] ?? 10;
    }

    /**
     * Get auto-cancel time after late arrival
     */
    public function getAutoCancelAfterMinutes(string $serviceId, string $salonId): int
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        return $cancellationPolicy['auto_cancel_after_minutes'] ?? 15;
    }

    /**
     * Check if rescheduling is allowed
     */
    public function canReschedule(string $serviceId, string $salonId, Carbon $bookingTime): array
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        $freeCancellationHours = $cancellationPolicy['free_cancellation_hours'] ?? 24;

        $now = Carbon::now();
        $hoursUntilBooking = $now->diffInHours($bookingTime, false);

        if ($hoursUntilBooking < 0) {
            return [
                'allowed' => false,
                'reason' => 'Booking time has passed',
            ];
        }

        // Use same rules as cancellation for rescheduling
        if ($hoursUntilBooking >= $freeCancellationHours) {
            return [
                'allowed' => true,
                'reason' => 'Free rescheduling allowed',
                'free_reschedule' => true,
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'Rescheduling allowed (may incur fee)',
            'free_reschedule' => false,
        ];
    }

    /**
     * Get cancellation policy summary for display
     */
    public function getCancellationPolicySummary(string $serviceId, string $salonId): array
    {
        $cancellationPolicy = $this->configService->getPolicy($serviceId, $salonId, 'cancellation_policy');
        $freeCancellationHours = $cancellationPolicy['free_cancellation_hours'] ?? 24;
        $lateFeeType = $cancellationPolicy['late_cancellation_fee'] ?? 'deposit';

        return [
            'free_cancellation_hours' => $freeCancellationHours,
            'free_cancellation_text' => $this->formatFreeCancellationText($freeCancellationHours),
            'late_cancellation_fee_type' => $lateFeeType,
            'late_cancellation_text' => $this->formatLateCancellationText($lateFeeType),
            'no_show_policy' => $cancellationPolicy['no_show_policy'] ?? [],
        ];
    }

    /**
     * Format free cancellation text for display
     */
    protected function formatFreeCancellationText(int $hours): string
    {
        if ($hours === 0) {
            return 'No free cancellation';
        }

        if ($hours < 24) {
            return "Free cancellation up to {$hours} hours before booking";
        }

        $days = floor($hours / 24);
        return "Free cancellation up to {$days} day(s) before booking";
    }

    /**
     * Format late cancellation text for display
     */
    protected function formatLateCancellationText(string $feeType): string
    {
        switch ($feeType) {
            case 'deposit':
                return 'Late cancellations forfeit deposit';
            case 'percentage':
                return 'Late cancellations incur percentage fee';
            case 'fixed':
                return 'Late cancellations incur fixed fee';
            case 'none':
                return 'No late cancellation fee';
            default:
                return 'Late cancellation fees may apply';
        }
    }
}
