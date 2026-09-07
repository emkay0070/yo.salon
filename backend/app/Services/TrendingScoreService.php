<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Specialist;
use Illuminate\Support\Facades\DB;

class TrendingScoreService
{
    /**
     * Calculate trending score for a salon
     * Formula: (booking_count * 0.4) + (rating * 10 * 0.3) + (view_count * 0.2) + (recent_booking_weight * 0.1)
     */
    public function calculateSalonScore(Salon $salon): float
    {
        $bookingCount = DB::table('bookings')
            ->where('salon_id', $salon->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        $recentBookings = DB::table('bookings')
            ->where('salon_id', $salon->id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $rating = $salon->rating ?? 0;
        $viewCount = $salon->view_count ?? 0;
        $reviewCount = $salon->review_count ?? 0;

        // Time decay factor - recent activity gets more weight
        $recentWeight = $recentBookings * 2;

        // Calculate score
        $score = ($bookingCount * 0.4) + ($rating * 10 * 0.3) + ($viewCount * 0.2) + ($recentWeight * 0.1);

        // Bonus for having reviews
        if ($reviewCount > 0) {
            $score += min($reviewCount * 0.5, 10);
        }

        return round($score, 2);
    }

    /**
     * Calculate trending score for a service
     */
    public function calculateServiceScore(Service $service): float
    {
        $bookingCount = DB::table('bookings')
            ->where('service_id', $service->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        $recentBookings = DB::table('bookings')
            ->where('service_id', $service->id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $rating = $service->rating ?? 0;
        $viewCount = $service->view_count ?? 0;
        $reviewCount = $service->review_count ?? 0;

        $recentWeight = $recentBookings * 2;

        $score = ($bookingCount * 0.4) + ($rating * 10 * 0.3) + ($viewCount * 0.2) + ($recentWeight * 0.1);

        if ($reviewCount > 0) {
            $score += min($reviewCount * 0.5, 10);
        }

        return round($score, 2);
    }

    /**
     * Calculate trending score for a specialist
     */
    public function calculateSpecialistScore(Specialist $specialist): float
    {
        $bookingCount = DB::table('bookings')
            ->where('specialist_id', $specialist->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        $recentBookings = DB::table('bookings')
            ->where('specialist_id', $specialist->id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $rating = $specialist->rating ?? 0;
        $viewCount = $specialist->view_count ?? 0;
        $followerCount = $specialist->follower_count ?? 0;
        $reviewCount = $specialist->review_count ?? 0;

        $recentWeight = $recentBookings * 2;

        // Specialists get extra weight from followers
        $followerWeight = min($followerCount * 0.1, 20);

        $score = ($bookingCount * 0.35) + ($rating * 10 * 0.25) + ($viewCount * 0.15) + ($recentWeight * 0.15) + ($followerWeight * 0.1);

        if ($reviewCount > 0) {
            $score += min($reviewCount * 0.5, 10);
        }

        return round($score, 2);
    }

    /**
     * Refresh trending scores for all salons
     */
    public function refreshAllSalonScores(): void
    {
        Salon::where('active', true)->chunk(100, function ($salons) {
            foreach ($salons as $salon) {
                $salon->trending_score = $this->calculateSalonScore($salon);
                $salon->saveQuietly();
            }
        });
    }

    /**
     * Refresh trending scores for all services
     */
    public function refreshAllServiceScores(): void
    {
        Service::where('active', true)->chunk(100, function ($services) {
            foreach ($services as $service) {
                $service->trending_score = $this->calculateServiceScore($service);
                $service->saveQuietly();
            }
        });
    }

    /**
     * Refresh trending scores for all specialists
     */
    public function refreshAllSpecialistScores(): void
    {
        Specialist::where('active', true)->chunk(100, function ($specialists) {
            foreach ($specialists as $specialist) {
                $specialist->trending_score = $this->calculateSpecialistScore($specialist);
                $specialist->saveQuietly();
            }
        });
    }

    /**
     * Refresh all trending scores
     */
    public function refreshAll(): void
    {
        $this->refreshAllSalonScores();
        $this->refreshAllServiceScores();
        $this->refreshAllSpecialistScores();
    }
}
