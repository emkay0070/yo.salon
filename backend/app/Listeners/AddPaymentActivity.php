<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\BookingActivity;

class AddPaymentActivity
{
    public function handle(PaymentConfirmed $event)
    {
        BookingActivity::create([
            'booking_id' => $event->bookingId,
            'type' => 'payment_confirmed',
            'title' => 'Payment Confirmed',
            'description' => "Payment of {$event->amount} UGX received via {$event->paymentMethod}",
            'data' => [
                'amount' => $event->amount,
                'payment_method' => $event->paymentMethod,
            ],
            'actor_type' => 'system',
        ]);
    }
}
