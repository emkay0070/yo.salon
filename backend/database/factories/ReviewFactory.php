<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => \App\Models\Booking::factory(),
            'provider_id' => null,
            'service_id' => null,
            'staff_id' => null,
            'specialist_id' => null,
            'customer_id' => \App\Models\Customer::factory(),
            'subject_type' => fake()->randomElement(['service', 'specialist', 'provider']),
            'subject_id' => fake()->uuid(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
