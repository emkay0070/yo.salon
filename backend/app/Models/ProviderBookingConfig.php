<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProviderBookingConfig extends Model
{
    use HasUuids;

    protected $table = 'provider_booking_config';

    protected $fillable = [
        'provider_id',
        'availability_policy',
        'assignment_policy',
        'payment_policy',
        'cancellation_policy',
        'approval_policy',
        'booking_policy',
    ];

    protected $casts = [
        'availability_policy' => 'array',
        'assignment_policy' => 'array',
        'payment_policy' => 'array',
        'cancellation_policy' => 'array',
        'approval_policy' => 'array',
        'booking_policy' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Get default availability policy
     */
    public function getDefaultAvailabilityPolicy(): array
    {
        return $this->availability_policy ?? [
            'max_advance_booking_days' => 30,
            'min_notice_hours' => 2,
            'allow_walk_ins' => false,
            'allow_same_day' => true,
            'buffer_minutes' => 15,
            'max_bookings_per_day' => null,
            'blackout_dates' => [],
        ];
    }

    /**
     * Get default assignment policy
     */
    public function getDefaultAssignmentPolicy(): array
    {
        return $this->assignment_policy ?? [
            'strategy' => 'customer_choice', // customer_choice, auto_assign, reception_assign, none
            'auto_assign_criteria' => 'availability', // availability, rating, load_balance
            'show_specialist_photos' => true,
            'show_specialist_ratings' => true,
            'allow_specialist_change' => true,
        ];
    }

    /**
     * Get default payment policy
     */
    public function getDefaultPaymentPolicy(): array
    {
        return $this->payment_policy ?? [
            'strategy' => 'no_payment', // no_payment, deposit, full_payment, pay_at_salon
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
        ];
    }

    /**
     * Get default cancellation policy
     */
    public function getDefaultCancellationPolicy(): array
    {
        return $this->cancellation_policy ?? [
            'free_cancellation_hours' => 24,
            'late_cancellation_fee' => 'deposit', // deposit, percentage, fixed
            'late_cancellation_amount' => null,
            'no_show_policy' => [
                'first_offense' => 'warning',
                'second_offense' => 'deposit_required',
                'third_offense' => 'full_payment_required',
            ],
        ];
    }

    /**
     * Get default approval policy
     */
    public function getDefaultApprovalPolicy(): array
    {
        return $this->approval_policy ?? [
            'require_approval' => false,
            'approval_type' => 'instant', // instant, manual, consultation
            'consultation_required' => false,
            'consultation_duration_minutes' => 15,
            'auto_approve_after_hours' => null,
        ];
    }

    /**
     * Get default booking policy
     */
    public function getDefaultBookingPolicy(): array
    {
        return $this->booking_policy ?? [
            'max_bookings_per_customer_per_day' => 5,
            'allow_repeat_bookings' => true,
            'allow_double_booking' => false,
            'waiting_list_enabled' => false,
            'require_customer_profile' => false,
            'require_phone_number' => true,
        ];
    }
}
