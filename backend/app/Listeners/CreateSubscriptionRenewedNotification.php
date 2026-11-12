<?php

namespace App\Listeners;

use App\Events\SubscriptionRenewed;
use App\Models\Notification;

class CreateSubscriptionRenewedNotification
{
    public function handle(SubscriptionRenewed $event): void
    {
        Notification::create([
            'provider_id' => $event->providerId,
            'type' => 'subscription_renewed',
            'category' => 'billing',
            'priority' => 'normal',
            'icon' => 'check-circle',
            'title' => 'Subscription Renewed',
            'message' => 'Your subscription has been successfully renewed. Thank you for continuing with Yo.Salon!',
            'action_url' => '/dashboard/billing',
            'data' => [
                'subscription_id' => $event->subscriptionId,
                'invoice_id' => $event->invoiceId,
            ],
        ]);
    }
}
