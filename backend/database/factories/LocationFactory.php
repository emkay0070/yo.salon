<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->slug(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'is_primary' => true,
            'is_active' => true,
            'is_open' => true,
            'accepting_online_bookings' => true,
            'opening_hours' => [],
            'timezone' => $this->faker->timezone(),
        ];
    }
}
