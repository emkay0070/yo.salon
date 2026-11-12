<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GiftCard>
 */
class GiftCardFactory extends Factory
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
            'code' => \App\Models\GiftCard::generateCode(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'balance' => fn(array $attributes) => $attributes['amount'],
            'currency' => 'USD',
            'status' => fake()->randomElement(['created', 'available', 'partially_redeemed', 'fully_redeemed', 'expired', 'cancelled']),
            'purchased_by' => \App\Models\Customer::factory(),
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => fake()->optional(0.3, null)->dateTimeBetween('+1 month', '+1 year'),
            'issued_at' => fake()->optional(0.7, null)->dateTimeBetween('-1 month', 'now'),
            'cancelled_at' => null,
            'active' => true,
            'message' => fake()->optional(0.5)->sentence(),
            'metadata' => fake()->optional(0.3)->randomElement([
                ['recipient_name' => fake()->name()],
                ['gift_wrap' => true],
                null,
            ]),
        ];
    }

    /**
     * Indicate that the gift card is available
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'issued_at' => now(),
        ]);
    }

    /**
     * Indicate that the gift card is partially redeemed
     */
    public function partiallyRedeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partially_redeemed',
            'balance' => $attributes['amount'] * 0.5,
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Indicate that the gift card is fully redeemed
     */
    public function fullyRedeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'fully_redeemed',
            'balance' => 0,
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Indicate that the gift card is expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the gift card is cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'active' => false,
        ]);
    }
}
