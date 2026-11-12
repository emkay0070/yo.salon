<?php

namespace Database\Factories;

use App\Models\ResourceRequirement;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceRequirementFactory extends Factory
{
    protected $model = ResourceRequirement::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'resource_type' => $this->faker->randomElement(['room', 'equipment', 'station']),
            'required_capabilities' => null,
            'count' => 1,
            'min_capacity' => null,
        ];
    }
}
