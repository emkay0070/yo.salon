<?php

namespace App\Listeners;

use App\Events\GiftCardPaymentFailed;
use App\Models\Notification;

class CreateGiftCardPaymentFailedNotification
{
    public function handle(GiftCardPaymentFailed $event): void
    {
        $intent = $event->intent;
        $amountFormatted = number_format($intent->amount, 0);

        Notification::create([
            'customer_id' => $intent->customer_id,
            'type' => 'gift_card_payment_failed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'alert-triangle',
            'title' => 'Gift Card Payment Failed',
            'message' => "Your payment of {$amountFormatted} {$intent->currency} for a Gift Card could not be processed.",
            'action_url' => '/portal/gift-cards',
            'data' => [],
        ]);
    }
}
