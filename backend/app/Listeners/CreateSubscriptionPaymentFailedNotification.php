<?php

namespace App\Listeners;

use App\Events\SubscriptionPaymentFailed;
use App\Models\Notification;

class CreateSubscriptionPaymentFailedNotification
{
    public function handle(SubscriptionPaymentFailed $event): void
    {
        Notification::create([
            'provider_id' => $event->providerId,
            'type' => 'subscription_payment_failed',
            'category' => 'billing',
            'priority' => 'high',
            'icon' => 'alert-triangle',
            'title' => 'Subscription Payment Failed',
            'message' => 'Your recent subscription payment attempt failed. Please check your payment method.',
            'action_url' => '/dashboard/billing/invoices',
            'data' => [
                'subscription_id' => $event->subscriptionId,
                'invoice_id' => $event->invoiceId,
            ],
        ]);
    }
}
