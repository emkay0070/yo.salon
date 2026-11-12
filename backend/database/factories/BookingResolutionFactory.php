<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingResolutionFactory extends Factory
{
    protected $model = BookingResolution::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'outcome' => $this->faker->randomElement(['NO_SHOW', 'CANCELLED', 'RESCHEDULED', 'COMPLETED_LATE', 'CUSTOMER_ARRIVED']),
            'reason_code' => $this->faker->randomElement([
                'CUSTOMER_DID_NOT_ARRIVE',
                'CUSTOMER_CANCELLED',
                'CUSTOMER_REQUESTED_RESCHEDULE',
                'CUSTOMER_COULD_NOT_BE_REACHED',
                'CUSTOMER_ARRIVED_LATE',
                'PROVIDER_UNAVAILABLE',
                'SPECIALIST_UNAVAILABLE',
                'LOCATION_CLOSED',
                'TECHNICAL_ISSUE',
                'OTHER',
            ]),
            'notes' => $this->faker->optional()->text(),
            'actor_type' => $this->faker->randomElement(['CUSTOMER', 'SPECIALIST', 'STAFF', 'PROVIDER', 'SYSTEM']),
            'actor_id' => $this->faker->optional()->uuid(),
            'resolved_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'follow_up_required' => $this->faker->boolean(20), // 20% chance of requiring follow-up
            'follow_up_at' => $this->faker->optional()->dateTimeBetween('now', '+1 week'),
            'follow_up_status' => $this->faker->optional()->randomElement(['PENDING', 'SENT', 'RESPONDED', 'COMPLETED']),
            'follow_up_notes' => $this->faker->optional()->text(),
        ];
    }
}
