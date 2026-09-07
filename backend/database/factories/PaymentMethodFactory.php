<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'salon_id' => $this->faker->uuid(),
            'provider' => $this->faker->randomElement(['flutterwave', 'mtn', 'airtel', 'stripe', 'cash']),
            'type' => $this->faker->randomElement(['card', 'mobile_money', 'cash']),
            'display_name' => $this->faker->word(),
            'environment' => 'sandbox',
            'is_active' => true,
            'payment_account_id' => null,
        ];
    }

    public function forSalon(Salon $salon): self
    {
        return $this->state(fn (array $attributes) => [
            'salon_id' => $salon->id,
        ]);
    }

    public function forProvider(string $provider): self
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }

    public function cash(): self
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'cash',
            'type' => 'cash',
        ]);
    }

    public function mobileMoney(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'mobile_money',
        ]);
    }

    public function card(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'card',
        ]);
    }
}
