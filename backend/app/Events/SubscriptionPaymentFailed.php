<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $providerId;
    public string $subscriptionId;
    public string $invoiceId;

    public function __construct(string $providerId, string $subscriptionId, string $invoiceId)
    {
        $this->providerId = $providerId;
        $this->subscriptionId = $subscriptionId;
        $this->invoiceId = $invoiceId;
    }
}
