<?php

namespace App\Listeners;

use App\Events\RefundConfirmed;
use App\Models\Notification;

/**
 * Creates provider + customer in-app notifications when a RefundConfirmed
 * event is dispatched. Mirrors the CreatePaymentNotification pattern to keep
 * money-event notifications symmetric (payment confirmed ↔ refund confirmed).
 */
class CreateRefundNotification
{
    public function handle(RefundConfirmed $event): void
    {
        $currency = $event->currency ?? 'UGX';
        $amountFormatted = number_format((float) $event->refundAmount) . ' ' . $currency;
        $paymentMethod = $event->refundTransaction->paymentMethod?->name ?? 'MTN MoMo';
        $bookingId = $event->booking->id;

        Notification::create([
            'provider_id' => $event->providerId,
            'type' => 'refund_confirmed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet-refund',
            'title' => 'Refund Processed',
            'message' => "A refund of {$amountFormatted} has been processed via {$paymentMethod}",
            'action_url' => "/dashboard/bookings/{$bookingId}",
            'data' => [
                'booking_id' => $bookingId,
                'refund_transaction_id' => $event->refundTransaction->id,
                'original_transaction_id' => $event->originalTransaction->id,
                'amount' => $event->refundAmount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
            ],
        ]);

        Notification::create([
            'customer_id' => $event->customerId,
            'type' => 'refund_confirmed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet-refund',
            'title' => 'Refund Confirmed',
            'message' => "Your refund of {$amountFormatted} has been confirmed via {$paymentMethod}",
            'action_url' => "/portal/bookings/{$bookingId}",
            'data' => [
                'booking_id' => $bookingId,
                'refund_transaction_id' => $event->refundTransaction->id,
                'original_transaction_id' => $event->originalTransaction->id,
                'amount' => $event->refundAmount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
            ],
        ]);
    }

}
