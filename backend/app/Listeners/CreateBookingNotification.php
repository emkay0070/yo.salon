<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\Notification;

class CreateBookingNotification
{
    public function handle(BookingCreated $event)
    {
        // Create notification for salon
        Notification::create([
            'salon_id' => $event->salonId,
            'type' => 'booking_created',
            'category' => 'booking',
            'priority' => 'high',
            'icon' => 'calendar',
            'title' => 'New Booking',
            'message' => "New booking from {$event->customerName} for {$event->serviceNames}",
            'action_url' => "/dashboard/bookings/{$event->bookingId}",
            'data' => [
                'booking_id' => $event->bookingId,
                'customer_name' => $event->customerName,
                'service_names' => $event->serviceNames,
                'date' => $event->date,
                'time' => $event->time,
            ],
        ]);

        // Create notification for customer
        Notification::create([
            'customer_id' => $event->customerId,
            'type' => 'booking_created',
            'category' => 'booking',
            'priority' => 'normal',
            'icon' => 'calendar',
            'title' => 'Booking Submitted',
            'message' => "Your booking for {$event->serviceNames} has been submitted",
            'action_url' => "/portal/bookings/{$event->bookingId}",
            'data' => [
                'booking_id' => $event->bookingId,
                'service_names' => $event->serviceNames,
                'date' => $event->date,
                'time' => $event->time,
            ],
        ]);
    }

    public function __invoke(BookingCreated $event)
    {
        return $this->handle($event);
    }
}
