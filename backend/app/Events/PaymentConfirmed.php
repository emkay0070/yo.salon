<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public $booking;
    public $transaction;
    public $customerId;
    public $salonId;
    public $amount;

    public function __construct(Booking $booking, Transaction $transaction, $customerId)
    {
        $this->booking = $booking;
        $this->transaction = $transaction;
        $this->customerId = $customerId;
        $this->salonId = $booking->salon_id;
        $this->amount = $transaction->gross_amount;
    }
}
