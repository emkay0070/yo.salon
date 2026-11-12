<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\GiftCardPurchaseIntent;

class GiftCardPaymentConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public GiftCardPurchaseIntent $intent;

    /**
     * Create a new event instance.
     */
    public function __construct(GiftCardPurchaseIntent $intent)
    {
        $this->intent = $intent;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
