<?php

namespace App\Domain\Payments\Factories;

use App\Domain\Payments\DTOs\PaymentInstruction;

class PaymentInstructionFactory
{
    /**
     * Create a PaymentInstruction DTO from calculated payment details.
     */
    public function create(
        string $strategy,
        array $total,
        array $deposit,
        int $cancellationHours
    ): PaymentInstruction {
        $instruction = new PaymentInstruction();
        $instruction->strategy = $strategy;
        $instruction->totalAmount = $total['total'];
        $instruction->amountDueNow = $total['payment_at_booking'];
        $instruction->amountRemaining = $total['remaining'];
        $instruction->paymentRequiredAtBooking = $total['payment_required'];
        $instruction->currency = 'UGX'; // TODO: Get from salon settings if multi-currency supported
        $instruction->expiresInMinutes = 10;
        
        $instruction->cancellationPolicy = [
            'free_cancellation_hours' => $cancellationHours,
            'deposit_refundable' => $deposit['refundable'],
            'refund_deadline_hours' => $deposit['refund_deadline_hours'] ?? null,
        ];

        // Determine Customer Action & Message
        switch ($strategy) {
            case 'deposit':
                $instruction->customerAction = 'pay_now';
                $instruction->message = "Deposit of {$instruction->amountDueNow} {$instruction->currency} required to confirm booking. Remaining balance of {$instruction->amountRemaining} {$instruction->currency} payable at salon.";
                break;
            case 'full_payment':
                $instruction->customerAction = 'pay_now';
                $instruction->message = "Full payment of {$instruction->totalAmount} {$instruction->currency} required to confirm booking.";
                break;
            case 'pay_at_salon':
                $instruction->customerAction = 'pay_at_salon';
                $instruction->message = 'Reservation confirmed. Pay at the salon.';
                break;
            case 'no_payment':
            default:
                $instruction->customerAction = 'none';
                $instruction->message = 'No payment required.';
                break;
        }

        return $instruction;
    }
}
