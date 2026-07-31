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
        
        $bookingId = null;
        $salonId = null;

        // Extract booking ID and salon ID from different event types
        if (property_exists($event, 'booking')) {
            $bookingId = $event->booking->id;
            $salonId = $event->booking->salon_id;
        } elseif (property_exists($event, 'bookingId')) {
            $bookingId = $event->bookingId;
        }
        
        if (property_exists($event, 'salonId')) {
            $salonId = $event->salonId;
        }

        Log::info('Event broadcasted', [
            'event' => get_class($event),
            'booking_id' => $bookingId,
            'salon_id' => $salonId,
        ]);

        // Invalidate dashboard stats cache when relevant events occur
        if ($salonId) {
            $dashboardController = new DashboardController();
            $dashboardController->invalidateStatsCache($salonId);
        }
    }
}
