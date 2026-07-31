<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated
{
    use Dispatchable, SerializesModels;

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
}
