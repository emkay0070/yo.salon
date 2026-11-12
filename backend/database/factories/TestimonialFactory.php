<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => \App\Models\Provider::factory(),
            'location_id' => \App\Models\Location::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->optional()->safeEmail(),
            'rating' => fake()->numberBetween(1, 5),
            'content' => fake()->paragraph(),
            'visible' => false,
            'consent_given' => false,
            'consent_date' => null,
            'status' => 'pending',
        ];
    }
}
