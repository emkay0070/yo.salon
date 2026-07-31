<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\Notification;

class CreatePaymentNotification
{
    public function handle(PaymentConfirmed $event)
    {
        $paymentMethod = $event->transaction->paymentMethod?->name ?? 'MTN MoMo';

        // Create notification for salon
        Notification::create([
            'salon_id' => $event->salonId,
            'type' => 'payment_confirmed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet',
            'title' => 'Payment Received',
            'message' => "Payment of {$event->amount} UGX received via {$paymentMethod}",
            'action_url' => "/dashboard/bookings/{$event->booking->id}",
            'data' => [
                'booking_id' => $event->booking->id,
                'amount' => $event->amount,
                'payment_method' => $paymentMethod,
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
            'action_url' => "/portal/bookings/{$event->booking->id}",
            'data' => [
                'booking_id' => $event->booking->id,
                'amount' => $event->amount,
                'payment_method' => $paymentMethod,
            ],
        ]);
    }

    public function __invoke(PaymentConfirmed $event)
    {
        return $this->handle($event);
    }
}
