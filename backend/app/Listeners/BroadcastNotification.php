<?php

namespace App\Listeners;

use App\Http\Controllers\Api\V1\DashboardController;
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

        // Invalidate dashboard stats cache when relevant events occur
        if (property_exists($event, 'salonId')) {
            $dashboardController = new DashboardController();
            $dashboardController->invalidateStatsCache($event->salonId);
        }
    }
}
