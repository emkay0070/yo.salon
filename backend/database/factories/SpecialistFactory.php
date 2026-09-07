<?php

namespace Database\Factories;

use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialist>
 */
class SpecialistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
            'handle' => fake()->userName(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'specialties' => ['Haircut', 'Beard Trim'],
            'languages' => ['English'],
            'years_experience' => fake()->numberBetween(1, 20),
            'rating' => fake()->randomFloat(1, 3, 5),
            'review_count' => fake()->numberBetween(0, 100),
            'active' => true,
            'verification_status' => 'verified',
            'punctuality' => fake()->randomFloat(1, 80, 100),
            'attendance' => fake()->randomFloat(1, 80, 100),
            'response_rate' => fake()->randomFloat(1, 80, 100),
        ];
    }
}
