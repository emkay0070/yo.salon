<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        return [
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory()->create(['provider_id' => $provider->id]),
            'date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'time' => $this->faker->time('H:i'),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ];
    }
}
