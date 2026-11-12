<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BookingServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expectedEndAt = $this->calculateExpectedEndAt();
        $overrun = $this->calculateOverrun($expectedEndAt);

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'service_id' => $this->service_id,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'session_status' => $this->session_status,
            'started_at' => $this->started_at,
            'actual_end_at' => $this->actual_end_at,
            'expected_end_at' => $expectedEndAt,
            'overrun' => $overrun,
        ];
    }

    /**
     * Calculate expected end time using authoritative duration resolution.
     * Uses LocationServiceCatalogResolver (same as Session 2).
     */
    private function calculateExpectedEndAt(): ?string
    {
        // Cancelled sessions have no expected end time
        if ($this->session_status === 'cancelled') {
            return null;
        }

        if (!$this->started_at) {
            return null;
        }

        $catalogResolver = new \App\Services\LocationServiceCatalogResolver();
        $booking = $this->booking;
        $location = $booking->location;

        if (!$location) {
            // Fallback to base service duration
            $service = $this->service;
            $effectiveDuration = $service->duration;
        } else {
            // Use location-specific duration override if present
            $resolvedService = $catalogResolver->queryAllServices($location)
                ->where('services.id', $this->service_id)
                ->first();

            if ($resolvedService && isset($resolvedService->pivot_duration_override)) {
                $effectiveDuration = $resolvedService->pivot_duration_override;
            } else {
                $effectiveDuration = $this->service->duration;
            }
        }

        return Carbon::parse($this->started_at)->addMinutes($effectiveDuration)->toIso8601String();
    }

    /**
     * Calculate overrun based on session status and timestamps.
     */
    private function calculateOverrun(?string $expectedEndAt): ?string
    {
        // Cancelled sessions have no overrun
        if ($this->session_status === 'cancelled') {
            return null;
        }

        if (!$expectedEndAt) {
            return null;
        }

        $expectedEnd = Carbon::parse($expectedEndAt);

        switch ($this->session_status) {
            case 'scheduled':
                return null;

            case 'in_progress':
                $now = Carbon::now();
                if ($now->gt($expectedEnd)) {
                    return $now->diff($expectedEnd)->format('%H:%I:%S');
                }
                return null;

            case 'completed':
                if ($this->actual_end_at) {
                    $actualEnd = Carbon::parse($this->actual_end_at);
                    if ($actualEnd->gt($expectedEnd)) {
                        return $actualEnd->diff($expectedEnd)->format('%H:%I:%S');
                    }
                }
                return null;

            default:
                return null;
        }
    }
}
