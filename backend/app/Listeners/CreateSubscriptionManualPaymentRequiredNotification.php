<?php

namespace App\Listeners;

use App\Events\SubscriptionManualPaymentRequired;
use App\Models\Notification;

class CreateSubscriptionManualPaymentRequiredNotification
{
    public function handle(SubscriptionManualPaymentRequired $event): void
    {
        Notification::create([
            'provider_id' => $event->providerId,
            'type' => 'subscription_manual_payment_required',
            'category' => 'billing',
            'priority' => 'high',
            'icon' => 'alert-circle',
            'title' => 'Action Required: Subscription Payment',
            'message' => 'Action Required: Automatic collection failed. Please process your subscription payment manually to prevent service interruption.',
            'action_url' => '/dashboard/billing/payment',
            'data' => [
                'subscription_id' => $event->subscriptionId,
                'invoice_id' => $event->invoiceId,
            ],
        ]);
    }
}
