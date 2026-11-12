<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'provider_id' => \App\Models\Provider::factory(),
            'location_id' => \App\Models\Location::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
            'photo' => null,
            'active' => true,
            'commission_rate' => 0,
        ];
    }
}
