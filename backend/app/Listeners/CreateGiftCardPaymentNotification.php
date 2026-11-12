<?php

namespace App\Listeners;

use App\Events\GiftCardPaymentConfirmed;
use App\Models\Notification;

class CreateGiftCardPaymentNotification
{
    public function handle(GiftCardPaymentConfirmed $event): void
    {
        $intent = $event->intent;
        $amountFormatted = number_format($intent->amount, 0);

        // Notify Customer
        Notification::create([
            'customer_id' => $intent->customer_id,
            'type' => 'gift_card_payment_confirmed',
            'category' => 'payment',
            'priority' => 'normal',
            'icon' => 'gift',
            'title' => 'Gift Card Purchased',
            'message' => "Your payment of {$amountFormatted} {$intent->currency} for a Gift Card was successful.",
            'action_url' => '/portal/gift-cards',
            'data' => [],
        ]);

        // Notify Provider
        Notification::create([
            'provider_id' => $intent->provider_id,
            'type' => 'gift_card_payment_confirmed',
            'category' => 'payment',
            'priority' => 'normal',
            'icon' => 'gift',
            'title' => 'New Gift Card Purchased',
            'message' => "A customer purchased a Gift Card for {$amountFormatted} {$intent->currency}.",
            'action_url' => '/dashboard/gift-cards',
            'data' => [],
        ]);
    }
}
