<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $bookingId;

    public function __construct(string $bookingId)
    {
        $this->bookingId = $bookingId;
    }
}
