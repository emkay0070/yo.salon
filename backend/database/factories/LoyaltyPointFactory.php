<?php

namespace Database\Factories;

use App\Models\LoyaltyPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyPoint>
 */
class LoyaltyPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'provider_id' => \App\Models\Provider::factory(),
            'points_earned' => fake()->numberBetween(0, 100),
            'points_redeemed' => fake()->numberBetween(0, 50),
            'balance' => fake()->numberBetween(0, 500),
            'tier' => fake()->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'tier_progress' => fake()->numberBetween(0, 100),
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}
