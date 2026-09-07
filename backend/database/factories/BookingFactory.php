<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'provider_id' => Provider::factory(),
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'time' => $this->faker->time('H:i'),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ];
    }
}
