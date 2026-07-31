<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\Notification;
use App\Models\BookingActivity;

class CreatePaymentNotification
{
    public function handle(PaymentConfirmed $event)
    {
        // Create notification for salon
        Notification::create([
            'salon_id' => $event->salonId,
            'type' => 'payment_confirmed',
            'title' => 'Payment Received',
            'message' => "Payment of {$event->amount} UGX received via {$event->paymentMethod}",
            'data' => [
                'booking_id' => $event->bookingId,
                'amount' => $event->amount,
                'payment_method' => $event->paymentMethod,
            ],
        ]);

        // Create notification for customer
        Notification::create([
            'customer_id' => $event->customerId,
            'type' => 'payment_confirmed',
            'title' => 'Payment Confirmed',
            'message' => "Your payment of {$event->amount} UGX has been confirmed",
            'data' => [
                'booking_id' => $event->bookingId,
                'amount' => $event->amount,
                'payment_method' => $event->paymentMethod,
            ],
        ]);

        // Create activity timeline entry
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
