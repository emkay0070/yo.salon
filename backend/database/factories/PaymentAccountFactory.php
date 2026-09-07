<?php

namespace Database\Factories;

use App\Models\PaymentAccount;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAccountFactory extends Factory
{
    protected $model = PaymentAccount::class;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'specialist_id' => null,
            'provider' => $this->faker->randomElement(['flutterwave', 'mtn', 'airtel', 'stripe']),
            'account_type' => $this->faker->randomElement(['merchant', 'subaccount', 'routing', 'manual']),
            'external_account_id' => $this->faker->uuid(),
            'account_name' => $this->faker->company(),
            'verification_status' => $this->faker->randomElement(['pending', 'verified', 'failed', 'suspended']),
            'is_active' => true,
            'is_default' => false,
            'settlement_schedule' => 'daily',
            'settlement_currency' => 'UGX',
            'metadata' => null,
        ];
    }

    public function verified(): self
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'pending',
        ]);
    }

    public function suspended(): self
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'suspended',
            'is_active' => false,
        ]);
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function forProvider(string $provider): self
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }

    public function forSalon(Salon $salon): self
    {
        return $this->state(fn (array $attributes) => [
            'salon_id' => $salon->id,
        ]);
    }
}
