<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([Provider::TYPE_SALON, Provider::TYPE_SPECIALIST]),
            'status' => 'active',
            'display_name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraph(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->companyEmail(),
            'active' => true,
        ];
    }
}
