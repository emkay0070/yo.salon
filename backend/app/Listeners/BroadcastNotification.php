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
        $providerId = null;

        // Extract booking ID and provider ID from different event types
        if (property_exists($event, 'booking')) {
            $bookingId = $event->booking->id;
            $providerId = $event->booking->provider_id;
        } elseif (property_exists($event, 'bookingId')) {
            $bookingId = $event->bookingId;
        }
        
        if (property_exists($event, 'providerId')) {
            $providerId = $event->providerId;
        }

        Log::info('Event broadcasted', [
            'event' => get_class($event),
            'booking_id' => $bookingId,
            'provider_id' => $providerId,
        ]);

        // Invalidate dashboard stats cache when relevant events occur
        if ($providerId) {
            $dashboardController = app(DashboardController::class);
            $dashboardController->invalidateStatsCache($providerId);
        }
    }

    public function __invoke($event)
    {
        return $this->handle($event);
    }
}
