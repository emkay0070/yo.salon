<?php

namespace Database\Factories;

use App\Models\SpecialistSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialistSubscription>
 */
class SpecialistSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'specialist_id' => \App\Models\Specialist::factory(),
            'plan_id' => \App\Models\Plan::where('slug', 'specialist-free')->firstOrFail()->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
            'starts_at' => now(),
            'ends_at' => null,
            'renews_at' => now()->addMonth(),
            'cancelled_at' => null,
            'cancel_reason' => null,
            'metadata' => null,
            'is_over_limit' => false,
            'is_grandfathered' => false,
            'grandfathering_metadata' => null,
        ];
    }
}
