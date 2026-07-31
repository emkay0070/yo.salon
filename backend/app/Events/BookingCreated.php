<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bookingId;
    public $customerId;
    public $salonId;
    public $customerName;
    public $serviceNames;
    public $date;
    public $time;

    public function __construct($bookingId, $customerId, $salonId, $customerName, $serviceNames, $date, $time)
    {
        $this->bookingId = $bookingId;
        $this->customerId = $customerId;
        $this->salonId = $salonId;
        $this->customerName = $customerName;
        $this->serviceNames = $serviceNames;
        $this->date = $date;
        $this->time = $time;
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
        return 'booking.created';
    }
}
