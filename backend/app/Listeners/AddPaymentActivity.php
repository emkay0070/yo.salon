<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\BookingActivity;

class AddPaymentActivity
{
    public function handle(PaymentConfirmed $event)
    {
        $paymentMethod = $event->transaction->paymentMethod?->name ?? 'MTN MoMo';

        BookingActivity::create([
            'booking_id' => $event->booking->id,
            'type' => 'payment_confirmed',
            'title' => 'Payment Confirmed',
            'description' => "Payment of {$event->amount} UGX received via {$paymentMethod}",
            'data' => [
                'amount' => $event->amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $event->transaction->id,
            ],
            'actor_type' => 'system',
        ]);
    }
}
