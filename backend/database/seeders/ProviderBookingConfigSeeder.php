<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\ProviderBookingConfig;
use Illuminate\Database\Seeder;

class ProviderBookingConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = Provider::all();

        foreach ($providers as $provider) {
            ProviderBookingConfig::updateOrCreate(
                ['provider_id' => $provider->id],
                [
                    'availability_policy' => [
                        'max_advance_booking_days' => 30,
                        'min_notice_hours' => 2,
                        'allow_walk_ins' => false,
                        'allow_same_day' => true,
                        'buffer_minutes' => 15,
                        'max_bookings_per_day' => null,
                        'blackout_dates' => [],
                    ],
                    'assignment_policy' => [
                        'strategy' => 'customer_choice',
                        'auto_assign_criteria' => 'availability',
                        'show_specialist_photos' => true,
                        'show_specialist_ratings' => true,
                        'allow_specialist_change' => true,
                    ],
                    'payment_policy' => [
                        'strategy' => 'no_payment',
                        'deposit' => [
                            'type' => 'percentage',
                            'amount' => 20,
                            'refundable' => true,
                            'refund_deadline_hours' => 24,
                            'transferable' => false,
                        ],
                        'membership_included' => false,
                        'consultation_deposit' => [
                            'enabled' => false,
                            'amount' => 10000,
                            'deductible' => true,
                        ],
                    ],
                    'cancellation_policy' => [
                        'free_cancellation_hours' => 24,
                        'late_cancellation_fee' => 'deposit',
                        'late_cancellation_amount' => null,
                        'no_show_policy' => [
                            'first_offense' => 'warning',
                            'second_offense' => 'deposit_required',
                            'third_offense' => 'full_payment_required',
                        ],
                    ],
                    'approval_policy' => [
                        'require_approval' => false,
                        'approval_type' => 'instant',
                        'consultation_required' => false,
                        'consultation_duration_minutes' => 15,
                        'auto_approve_after_hours' => null,
                    ],
                    'booking_policy' => [
                        'max_bookings_per_customer_per_day' => 5,
                        'allow_repeat_bookings' => true,
                        'allow_double_booking' => false,
                        'waiting_list_enabled' => false,
                        'require_customer_profile' => false,
                        'require_phone_number' => true,
                    ],
                ]
            );
        }
    }
}
