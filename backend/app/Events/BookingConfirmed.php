<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingId;
    public $customerId;
    public $salonId;
    public $staffId;

    public function __construct($bookingId, $customerId, $salonId, $staffId = null)
    {
        $this->bookingId = $bookingId;
        $this->customerId = $customerId;
        $this->salonId = $salonId;
        $this->staffId = $staffId;
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
        return 'booking.confirmed';
    }
}
