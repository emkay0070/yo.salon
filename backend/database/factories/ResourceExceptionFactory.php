<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceExceptionFactory extends Factory
{
    protected $model = ResourceException::class;

    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory(),
            'date' => Carbon::now()->toDateString(),
            'day_of_week' => null,
            'exception_type' => $this->faker->randomElement(['maintenance', 'closure', 'training']),
            'start_time' => $this->faker->time('H:i:s'),
            'end_time' => $this->faker->time('H:i:s'),
            'reason' => $this->faker->optional()->sentence(),
        ];
    }
}
