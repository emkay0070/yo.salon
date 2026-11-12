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
            'provider_id' => \App\Models\Provider::factory(),
            'provider' => $this->faker->randomElement(['flutterwave', 'mtn', 'airtel', 'stripe', 'cash']),
            'type' => $this->faker->randomElement(['card', 'mobile_money', 'cash']),
            'display_name' => $this->faker->word(),
            'environment' => 'sandbox',
            'is_active' => true,
            'payment_account_id' => null,
        ];
    }

    public function forProvider(\App\Models\Provider $provider): self
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => $provider->id,
        ]);
    }

    public function usingProvider(string $provider): self
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
