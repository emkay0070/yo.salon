<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GiftCardTransaction>
 */
class GiftCardTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gift_card_id' => \App\Models\GiftCard::factory(),
            'type' => fake()->randomElement(['purchase', 'issue', 'redeem', 'adjustment', 'cancellation']),
            'amount' => fake()->randomFloat(2, 0, 500),
            'balance_after' => fake()->randomFloat(2, 0, 500),
            'description' => fake()->optional()->sentence(),
            'metadata' => fake()->optional(0.3)->randomElement([
                ['booking_id' => fake()->uuid()],
                ['reason' => 'Customer service adjustment'],
                null,
            ]),
            'performed_by' => null,
        ];
    }

    /**
     * Indicate that the transaction is a purchase
     */
    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'purchase',
            'description' => 'Gift card purchased',
        ]);
    }

    /**
     * Indicate that the transaction is a redemption
     */
    public function redeem(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'redeem',
            'description' => 'Gift card redemption',
        ]);
    }

    /**
     * Indicate that the transaction is an adjustment
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'description' => 'Balance adjustment',
        ]);
    }
}
