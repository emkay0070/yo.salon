<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceCapabilityFactory extends Factory
{
    protected $model = ResourceCapability::class;

    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory(),
            'capability' => $this->faker->randomElement(['massage', 'facial', 'hot_stone', 'haircut', 'manicure']),
            'level' => $this->faker->optional()->word(),
        ];
    }
}
