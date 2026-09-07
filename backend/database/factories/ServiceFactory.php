<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 20, 500),
            'duration' => $this->faker->randomElement([30, 45, 60, 90]),
            'category' => $this->faker->randomElement(['Haircut', 'Color', 'Treatment', 'Styling']),
            'active' => true,
        ];
    }
}
