<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingId;
    public $customerId;
    public $salonId;
    public $amount;
    public $paymentMethod;

    public function __construct($bookingId, $customerId, $salonId, $amount, $paymentMethod)
    {
        $this->bookingId = $bookingId;
        $this->customerId = $customerId;
        $this->salonId = $salonId;
        $this->amount = $amount;
        $this->paymentMethod = $paymentMethod;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('salon.' . $this->salonId),
            new PrivateChannel('customer.' . $this->customerId),
        ];
    }

    public function broadcastAs()
    {
        return 'payment.confirmed';
    }
}
