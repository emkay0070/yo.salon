<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\Notification;

class CreatePaymentNotification
{
    public function handle(PaymentConfirmed $event)
    {
        // Create notification for salon
        Notification::create([
            'salon_id' => $event->salonId,
            'type' => 'payment_confirmed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet',
            'title' => 'Payment Received',
            'message' => "Payment of {$event->amount} UGX received via {$event->paymentMethod}",
            'action_url' => "/dashboard/bookings/{$event->bookingId}",
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
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet',
            'title' => 'Payment Confirmed',
            'message' => "Your payment of {$event->amount} UGX has been confirmed",
            'action_url' => "/portal/bookings/{$event->bookingId}",
            'data' => [
                'booking_id' => $event->bookingId,
                'amount' => $event->amount,
                'payment_method' => $event->paymentMethod,
            ],
        ]);
    }
}
