<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServiceBookingConfig extends Model
{
    use HasUuids;

    protected $table = 'service_booking_config';

    protected $fillable = [
        'service_id',
        'availability_policy_override',
        'assignment_policy_override',
        'payment_policy_override',
        'cancellation_policy_override',
        'approval_policy_override',
        'booking_policy_override',
        'service_requirements',
    ];

    protected $casts = [
        'availability_policy_override' => 'array',
        'assignment_policy_override' => 'array',
        'payment_policy_override' => 'array',
        'cancellation_policy_override' => 'array',
        'approval_policy_override' => 'array',
        'booking_policy_override' => 'array',
        'service_requirements' => 'array',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Check if this service has overrides for a specific policy
     */
    public function hasOverride(string $policy): bool
    {
        return !empty($this->{$policy . '_override'});
    }

    /**
     * Get override for a specific policy
     */
    public function getOverride(string $policy): ?array
    {
        return $this->{$policy . '_override'};
    }

    /**
     * Get all overrides as an array
     */
    public function getOverrides(): array
    {
        return [
            'availability_policy' => $this->availability_policy_override,
            'assignment_policy' => $this->assignment_policy_override,
            'payment_policy' => $this->payment_policy_override,
            'cancellation_policy' => $this->cancellation_policy_override,
            'approval_policy' => $this->approval_policy_override,
            'booking_policy' => $this->booking_policy_override,
        ];
    }

    /**
     * Get service-specific requirements
     */
    public function getServiceRequirements(): array
    {
        return $this->service_requirements ?? [
            'require_consultation' => false,
            'consultation_duration_minutes' => 15,
            'require_specialist' => true,
            'allowed_specialist_roles' => null,
            'min_duration_minutes' => null,
            'max_duration_minutes' => null,
            'duration_options' => [],
        ];
    }
}
