<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => $this->faker->word(),
            'type' => $this->faker->randomElement(['room', 'equipment', 'station']),
            'capacity' => 1,
            'turnaround_time' => 0,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
