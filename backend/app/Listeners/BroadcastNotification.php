<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

class BroadcastNotification
{
    public function handle($event)
    {
        // Events already implement ShouldBroadcast and will be automatically broadcast
        // This listener exists for future extensibility (e.g., logging, analytics)
        Log::info('Event broadcasted', [
            'event' => get_class($event),
            'booking_id' => $event->bookingId ?? null,
        ]);
    }
}
