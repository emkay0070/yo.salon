<?php

namespace Database\Factories;

use App\Models\Salon;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalonFactory extends Factory
{
    protected $model = Salon::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->paragraph(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
        ];
    }
}
