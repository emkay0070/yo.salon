<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Booking;
use App\Models\Review;
use App\Models\CustomerSpecialist;

class JourneyMetricCalculator
{
    /**
     * Calculate current metrics from authoritative sources
     * Does NOT persist - that's the repository's job
     * Now provider/workspace-aware for accurate metrics
     */
    public function calculate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $scope = $assignment ? 'WORKPLACE' : 'SPECIALIST';
        $assignmentId = $assignment?->id;

        // Build base queries with scope filtering
        $bookingsQuery = Booking::where('specialist_id', $specialist->id);
        $reviewsQuery = Review::where('specialist_id', $specialist->id);
        $customerSpecialistQuery = CustomerSpecialist::where('specialist_id', $specialist->id);

        if ($scope === 'WORKPLACE' && $assignment) {
            $bookingsQuery->where('provider_id', $assignment->provider_id);
            $reviewsQuery->where('provider_id', $assignment->provider_id);
            $customerSpecialistQuery->where('provider_id', $assignment->provider_id);
        }

        // Calculate from authoritative sources
        return [
            'clients_served' => $customerSpecialistQuery->count(),
            'bookings_completed' => $bookingsQuery->where('status', 'completed')->count(),
            'rating' => $reviewsQuery->avg('rating') ?? 0,
            'reviews_count' => $reviewsQuery->count(),
            'repeat_client_rate' => $this->calculateRepeatRate($specialist, $assignment),
            'services_performed' => $bookingsQuery->where('status', 'completed')->count(),
            'experience_years' => $specialist->years_experience ?? 0,
        ];
    }

    private function calculateRepeatRate(Specialist $specialist, ?SpecialistAssignment $assignment): float
    {
        $query = CustomerSpecialist::where('specialist_id', $specialist->id);

        if ($assignment) {
            $query->where('provider_id', $assignment->provider_id);
        }

        $totalClients = $query->count();
        if ($totalClients === 0) return 0;

        $repeatClients = $query->where('total_bookings', '>', 1)->count();

        return $repeatClients / $totalClients;
    }
}
