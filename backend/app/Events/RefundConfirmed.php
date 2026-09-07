<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RefundConfirmed event is dispatched when a refund is successfully confirmed.
 * 
 * This event triggers the Finance Domain to:
 * - Reverse the payment recognition journal
 * - Post reversal ledger entries
 * - Handle settlement/payable implications
 */
class RefundConfirmed
{
    use Dispatchable, SerializesModels;

    public $originalTransaction;
    public $refundTransaction;
    public $booking;
    public $customerId;
    public $salonId;
    public $refundAmount;
    public $currency;

    public function __construct(
        Transaction $originalTransaction,
        Transaction $refundTransaction,
        Booking $booking,
        $customerId,
        $refundAmount,
        $currency = 'UGX'
    ) {
        $this->originalTransaction = $originalTransaction;
        $this->refundTransaction = $refundTransaction;
        $this->booking = $booking;
        $this->customerId = $customerId;
        $this->salonId = $booking->salon_id;
        $this->refundAmount = $refundAmount;
        $this->currency = $currency;
    }
}
