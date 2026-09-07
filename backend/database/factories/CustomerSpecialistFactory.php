<?php

namespace Database\Factories;

use App\Models\CustomerSpecialist;
use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerSpecialistFactory extends Factory
{
    protected $model = CustomerSpecialist::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'specialist_id' => Specialist::factory(),
            'provider_id' => Provider::factory(),
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
            'acquisition_source' => CustomerSpecialist::SOURCE_BOOKING,
            'total_bookings' => $this->faker->numberBetween(1, 20),
            'total_spent' => $this->faker->randomFloat(2, 50, 5000),
            'is_favorite' => false,
            'is_following' => false,
            'relationship_score' => $this->faker->numberBetween(1, 100),
        ];
    }
}
