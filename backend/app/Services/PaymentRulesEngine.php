<?php

namespace App\Services;

class PaymentRulesEngine
{
    private BookingConfigurationService $configService;

    public function __construct(BookingConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get payment strategy for a booking
     */
    public function getPaymentStrategy(string $serviceId, string $salonId): string
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        return $paymentPolicy['strategy'] ?? 'no_payment';
    }

    /**
     * Calculate deposit amount for a booking
     */
    public function calculateDeposit(string $serviceId, string $salonId, float $servicePrice): array
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        $strategy = $paymentPolicy['strategy'] ?? 'no_payment';
        $depositConfig = $paymentPolicy['deposit'] ?? [];

        if ($strategy === 'no_payment') {
            return [
                'required' => false,
                'amount' => 0,
                'type' => null,
                'refundable' => false,
            ];
        }

        if ($strategy === 'full_payment') {
            return [
                'required' => true,
                'amount' => $servicePrice,
                'type' => 'full',
                'refundable' => $depositConfig['refundable'] ?? true,
            ];
        }

        if ($strategy === 'deposit') {
            $depositAmount = $this->calculateDepositAmount($depositConfig, $servicePrice);
            
            return [
                'required' => true,
                'amount' => $depositAmount,
                'type' => $depositConfig['type'] ?? 'percentage',
                'refundable' => $depositConfig['refundable'] ?? true,
                'refund_deadline_hours' => $depositConfig['refund_deadline_hours'] ?? 24,
                'transferable' => $depositConfig['transferable'] ?? false,
            ];
        }

        if ($strategy === 'pay_at_salon') {
            return [
                'required' => false,
                'amount' => 0,
                'type' => 'pay_at_salon',
                'refundable' => false,
            ];
        }

        return [
            'required' => false,
            'amount' => 0,
            'type' => null,
            'refundable' => false,
        ];
    }

    /**
     * Calculate deposit amount based on configuration
     */
    protected function calculateDepositAmount(array $depositConfig, float $servicePrice): float
    {
        $type = $depositConfig['type'] ?? 'percentage';
        $amount = $depositConfig['amount'] ?? 20;

        if ($type === 'percentage') {
            return $servicePrice * ($amount / 100);
        }

        return $amount;
    }

    /**
     * Calculate total booking amount
     */
    public function calculateTotal(string $serviceId, string $salonId, float $servicePrice): array
    {
        $deposit = $this->calculateDeposit($serviceId, $salonId, $servicePrice);
        $strategy = $this->getPaymentStrategy($serviceId, $salonId);

        if ($strategy === 'full_payment') {
            return [
                'total' => $servicePrice,
                'deposit' => $servicePrice,
                'remaining' => 0,
                'payment_required' => true,
                'payment_at_booking' => $servicePrice,
            ];
        }

        if ($strategy === 'deposit') {
            return [
                'total' => $servicePrice,
                'deposit' => $deposit['amount'],
                'remaining' => $servicePrice - $deposit['amount'],
                'payment_required' => true,
                'payment_at_booking' => $deposit['amount'],
            ];
        }

        if ($strategy === 'no_payment' || $strategy === 'pay_at_salon') {
            return [
                'total' => $servicePrice,
                'deposit' => 0,
                'remaining' => $servicePrice,
                'payment_required' => false,
                'payment_at_booking' => 0,
            ];
        }

        return [
            'total' => $servicePrice,
            'deposit' => 0,
            'remaining' => $servicePrice,
            'payment_required' => false,
            'payment_at_booking' => 0,
        ];
    }

    /**
     * Check if deposit is refundable
     */
    public function isDepositRefundable(string $serviceId, string $salonId): bool
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        $depositConfig = $paymentPolicy['deposit'] ?? [];
        return $depositConfig['refundable'] ?? true;
    }

    /**
     * Get refund deadline in hours
     */
    public function getRefundDeadlineHours(string $serviceId, string $salonId): int
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        $depositConfig = $paymentPolicy['deposit'] ?? [];
        return $depositConfig['refund_deadline_hours'] ?? 24;
    }

    /**
     * Check if deposit is transferable
     */
    public function isDepositTransferable(string $serviceId, string $salonId): bool
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        $depositConfig = $paymentPolicy['deposit'] ?? [];
        return $depositConfig['transferable'] ?? false;
    }

    /**
     * Check if membership covers the booking
     */
    public function isMembershipIncluded(string $serviceId, string $salonId): bool
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        return $paymentPolicy['membership_included'] ?? false;
    }

    /**
     * Get consultation deposit configuration
     */
    public function getConsultationDeposit(string $serviceId, string $salonId): array
    {
        $paymentPolicy = $this->configService->getPolicy($serviceId, $salonId, 'payment_policy');
        $consultationConfig = $paymentPolicy['consultation_deposit'] ?? [];

        return [
            'enabled' => $consultationConfig['enabled'] ?? false,
            'amount' => $consultationConfig['amount'] ?? 10000,
            'deductible' => $consultationConfig['deductible'] ?? true,
        ];
    }

    /**
     * Generate payment instructions for customer
     */
    public function generatePaymentInstructions(string $serviceId, string $salonId, float $servicePrice): \App\Domain\Payments\DTOs\PaymentInstruction
    {
        $strategy = $this->getPaymentStrategy($serviceId, $salonId);
        $total = $this->calculateTotal($serviceId, $salonId, $servicePrice);
        $deposit = $this->calculateDeposit($serviceId, $salonId, $servicePrice);
        $cancellationHours = $this->configService->getFreeCancellationHours($serviceId, $salonId);

        $factory = new \App\Domain\Payments\Factories\PaymentInstructionFactory();
        return $factory->create($strategy, $total, $deposit, $cancellationHours);
    }

    /**
     * Calculate refund amount for cancellation
     */
    public function calculateRefundAmount(string $serviceId, string $salonId, float $depositAmount, \Carbon\Carbon $bookingTime): array
    {
        $refundable = $this->isDepositRefundable($serviceId, $salonId);
        $refundDeadlineHours = $this->getRefundDeadlineHours($serviceId, $salonId);
        $freeCancellationHours = $this->configService->getFreeCancellationHours($serviceId, $salonId);

        $now = \Carbon::now();
        $hoursUntilBooking = $now->diffInHours($bookingTime, false);

        if (!$refundable) {
            return [
                'refundable' => false,
                'refund_amount' => 0,
                'reason' => 'Deposit is non-refundable',
            ];
        }

        if ($hoursUntilBooking >= $freeCancellationHours) {
            return [
                'refundable' => true,
                'refund_amount' => $depositAmount,
                'reason' => 'Within free cancellation period',
            ];
        }

        if ($hoursUntilBooking >= $refundDeadlineHours) {
            return [
                'refundable' => true,
                'refund_amount' => $depositAmount,
                'reason' => 'Within refund deadline',
            ];
        }

        return [
            'refundable' => false,
            'refund_amount' => 0,
            'reason' => 'Past refund deadline',
        ];
    }
}
