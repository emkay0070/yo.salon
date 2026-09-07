<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Transaction;
use App\Models\Salon;
use App\Models\Booking;
use App\Models\PaymentMethod;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 1000, 50000);
        $fee = $gross * 0.03;

        return [
            'internal_reference' => 'TXN-' . strtoupper($this->faker->unique()->bothify('?????-#####')),
            'provider_reference' => $this->faker->uuid,
            'salon_id' => Salon::factory(),
            'booking_id' => Booking::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'type' => 'payment',
            'status' => 'successful',
            'currency' => 'UGX',
            'gross_amount' => $gross,
            'gateway_fee' => $fee,
            'platform_fee' => 0,
            'net_amount' => $gross - $fee,
        ];
    }
}
