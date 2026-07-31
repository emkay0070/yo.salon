<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\BookingActivity;

class AddBookingActivity
{
    public function handle(BookingCreated $event)
    {
        BookingActivity::create([
            'booking_id' => $event->bookingId,
            'type' => 'booking_created',
            'title' => 'Booking Created',
            'description' => "New booking created by {$event->customerName}",
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

    public function __invoke(BookingCreated $event)
    {
        return $this->handle($event);
    }
}
