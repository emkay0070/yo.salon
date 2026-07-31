<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\Notification;
use App\Models\BookingActivity;

class CreateBookingNotification
{
    public function handle(BookingCreated $event)
    {
        // Create notification for salon
        Notification::create([
            'salon_id' => $event->salonId,
            'type' => 'booking_created',
            'title' => 'New Booking',
            'message' => "New booking from {$event->customerName} for {$event->serviceNames}",
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
            'title' => 'Booking Submitted',
            'message' => "Your booking for {$event->serviceNames} has been submitted",
            'data' => [
                'booking_id' => $event->bookingId,
                'service_names' => $event->serviceNames,
                'date' => $event->date,
                'time' => $event->time,
            ],
        ]);

        // Create activity timeline entry
        BookingActivity::create([
            'booking_id' => $event->bookingId,
            'type' => 'created',
            'title' => 'Booking Created',
            'description' => "Booking submitted for {$event->serviceNames}",
            'data' => [
                'customer_name' => $event->customerName,
                'service_names' => $event->serviceNames,
                'date' => $event->date,
                'time' => $event->time,
            ],
            'actor_type' => 'customer',
            'actor_id' => $event->customerId,
        ]);
    }
}
