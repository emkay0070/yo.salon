<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\TimezoneResolverService;

class BookingResource extends JsonResource
{
    /**
     * Transform the booking resource into an array with canonical timezone-resolved data.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolver = app(TimezoneResolverService::class);
        
        // Resolve the business timezone for this booking
        $businessTimezone = $resolver->resolveBusinessTimezoneById(
            $this->provider_id,
            $this->location_id
        );
        
        // Parse the booking date/time in business timezone
        // $this->date is a Carbon object due to the date cast, so format it to YYYY-MM-DD
        $dateStr = $this->date ? $this->date->format('Y-m-d') : null;
        $bookingDateTime = $dateStr && $this->time
            ? \Illuminate\Support\Carbon::parse("{$dateStr} {$this->time}", $businessTimezone)
            : null;
        
        // Calculate end time from service duration if available
        $endTime = null;
        $durationMinutes = 0;
        
        if ($bookingDateTime && $this->service) {
            $durationMinutes = $this->service->duration ?? 0;
            if ($durationMinutes > 0) {
                $endTime = $bookingDateTime->copy()->addMinutes($durationMinutes);
            }
        } elseif ($bookingDateTime && $this->start_time && $this->end_time) {
            // Use stored start/end times if available
            $endTime = \Illuminate\Support\Carbon::parse("{$dateStr} {$this->end_time}", $businessTimezone);
        }
        
        return [
            'id' => $this->id,
            
            // Canonical scheduled information (business-local)
            'scheduled' => [
                'date' => $this->date?->format('Y-m-d'),
                'start_time' => $this->time,
                'end_time' => $endTime?->format('H:i'),
                'timezone' => $businessTimezone,
            ],
            
            // Absolute UTC timestamps for cross-timezone operations
            'starts_at' => $bookingDateTime?->toIso8601String(),
            'ends_at' => $endTime?->toIso8601String(),
            
            // Existing fields for backward compatibility
            'date' => $this->date?->format('Y-m-d'),
            'time' => $this->time,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            
            // Operational state (authoritative from backend)
            'status' => $this->status,
            'operational_state' => $this->operational_state,
            
            // Customer information
            'customer' => $this->whenLoaded('customer', fn() => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'phone' => $this->customer?->phone,
                'email' => $this->customer?->email,
            ]),
            
            // Provider context
            'provider' => $this->whenLoaded('provider', fn() => [
                'id' => $this->provider?->id,
                'name' => $this->provider?->name,
                'timezone' => $this->provider?->timezone,
            ]),
            
            // Location context
            'location' => $this->whenLoaded('location', fn() => [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
                'timezone' => $this->location?->timezone,
            ]),
            
            // Specialist information
            'specialist' => $this->whenLoaded('specialist', fn() => [
                'id' => $this->specialist?->id,
                'name' => $this->specialist?->name,
            ]),
            
            // Service information
            'service' => $this->whenLoaded('service', fn() => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'duration' => $this->service?->duration,
            ]),
            
            // Staff information
            'staff' => $this->whenLoaded('staff', fn() => [
                'id' => $this->staff?->id,
                'name' => $this->staff?->display_name,
            ]),
            
            // Resolution information
            'resolution' => $this->whenLoaded('resolution', fn() => [
                'id' => $this->resolution?->id,
                'outcome' => $this->resolution?->outcome,
                'reason_code' => $this->resolution?->reason_code,
            ]),
            
            // Payment information
            'payment_status' => $this->payment_status,
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'amount_remaining' => $this->amount_remaining,
            
            // Notes
            'notes' => $this->notes,
            
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Grace period tracking
            'grace_period_started_at' => $this->grace_period_started_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
        ];
    }
}
