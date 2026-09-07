<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * OperationalAnalyticsService
 * 
 * Provides canonical operational facts for the Intelligence Engine.
 * 
 * Principle: This service owns booking behavior facts, NOT revenue.
 * All revenue calculations must use FinanceAnalyticsService.
 */
class OperationalAnalyticsService
{
    /**
     * Get comprehensive operational facts for a salon
     * 
     * @param Salon $salon
     * @return array
     */
    public function getOperationalFacts(Salon $salon): array
    {
        $bookings = Booking::where('salon_id', $salon->id)
            ->with(['service', 'specialist', 'customer'])
            ->get();

        return [
            'totals' => $this->getTotals($bookings),
            'by_status' => $this->getByStatus($bookings),
            'by_service' => $this->getByService($bookings),
            'by_specialist' => $this->getBySpecialist($bookings),
            'by_customer' => $this->getByCustomer($bookings),
            'temporal' => $this->getTemporal($bookings),
            'today' => $this->getToday($salon),
            // Legacy-compatible fields for AnalyticsController
            'status_counts' => $this->getByStatus($bookings),
            'total_bookings' => $bookings->count(),
            'weekly_bookings' => $this->getWeeklyBookingsLegacy($bookings),
        ];
    }

    /**
     * Get total booking metrics
     */
    private function getTotals(Collection $bookings): array
    {
        $totalBookings = $bookings->count();
        $completedBookings = $bookings->where('status', 'completed')->count();
        $cancelledBookings = $bookings->where('status', 'cancelled')->count();
        $noShowBookings = $bookings->where('status', 'no_show')->count();
        $confirmedBookings = $bookings->where('status', 'confirmed')->count();

        $completionRate = $confirmedBookings > 0 
            ? round(($completedBookings / $confirmedBookings) * 100, 1) 
            : 0;
        
        $cancellationRate = $totalBookings > 0 
            ? round(($cancelledBookings / $totalBookings) * 100, 1) 
            : 0;
        
        $noShowRate = $confirmedBookings > 0 
            ? round(($noShowBookings / $confirmedBookings) * 100, 1) 
            : 0;

        return [
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'no_show_bookings' => $noShowBookings,
            'confirmed_bookings' => $confirmedBookings,
            'completion_rate' => $completionRate,
            'cancellation_rate' => $cancellationRate,
            'no_show_rate' => $noShowRate,
        ];
    }

    /**
     * Get bookings grouped by status
     */
    private function getByStatus(Collection $bookings): array
    {
        return $bookings->groupBy('status')->mapWithKeys(function ($group, $status) {
            return [$status => $group->count()];
        })->toArray();
    }

    /**
     * Get bookings grouped by service
     */
    private function getByService(Collection $bookings): array
    {
        return $bookings->whereNotNull('service_id')
            ->groupBy('service_id')
            ->map(function ($group) {
                $service = $group->first()->service;
                $completedCount = $group->where('status', 'completed')->count();
                
                return [
                    'service_id' => $service?->id,
                    'service_name' => $service?->name,
                    'booking_count' => $group->count(),
                    'completed_count' => $completedCount,
                    'completion_rate' => $group->count() > 0 
                        ? round(($completedCount / $group->count()) * 100, 1) 
                        : 0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get bookings grouped by specialist
     */
    private function getBySpecialist(Collection $bookings): array
    {
        return $bookings->whereNotNull('specialist_id')
            ->groupBy('specialist_id')
            ->map(function ($group) {
                $specialist = $group->first()->specialist;
                $completedCount = $group->where('status', 'completed')->count();
                
                return [
                    'specialist_id' => $specialist?->id,
                    'specialist_name' => $specialist?->name,
                    'booking_count' => $group->count(),
                    'completed_count' => $completedCount,
                    'completion_rate' => $group->count() > 0 
                        ? round(($completedCount / $group->count()) * 100, 1) 
                        : 0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get bookings grouped by customer
     */
    private function getByCustomer(Collection $bookings): array
    {
        return $bookings->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->map(function ($group) {
                $customer = $group->first()->customer;
                $bookingCount = $group->count();
                
                return [
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'booking_count' => $bookingCount,
                    'is_repeat_customer' => $bookingCount > 1,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get temporal booking patterns
     */
    private function getTemporal(Collection $bookings): array
    {
        // Weekly bookings (by day of week)
        $weeklyBookings = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
            ->mapWithKeys(function ($day) use ($bookings) {
                return [$day => $bookings->filter(
                    fn($b) => Carbon::parse($b->date)->format('l') === $day
                )->count()];
            })
            ->toArray();

        // Hourly bookings (by hour)
        $hourlyBookings = $bookings->whereNotNull('time')
            ->groupBy(function ($booking) {
                return Carbon::parse($booking->time)->format('H');
            })
            ->map->count()
            ->toArray();

        // Daily trend (last 30 days)
        $dailyTrend = $bookings->groupBy('date')
            ->map->count()
            ->sortBy('date')
            ->take(30)
            ->toArray();

        return [
            'weekly_bookings' => $weeklyBookings,
            'hourly_bookings' => $hourlyBookings,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Get today's operational stats
     */
    private function getToday(Salon $salon): array
    {
        $today = Carbon::today();
        $todayDate = $today->toDateString();
        $todayTime = $today->format('H:i:s');

        $newBookingsToday = Booking::where('salon_id', $salon->id)
            ->whereDate('created_at', $todayDate)
            ->count();

        $paymentsToday = Booking::where('salon_id', $salon->id)
            ->where('payment_status', 'paid')
            ->whereDate('updated_at', $todayDate)
            ->count();

        $awaitingApproval = Booking::where('salon_id', $salon->id)
            ->where('status', 'pending')
            ->whereDate('date', '>=', $todayDate)
            ->count();

        $customersWaiting = Booking::where('salon_id', $salon->id)
            ->where('status', 'confirmed')
            ->whereDate('date', $todayDate)
            ->where('time', '>=', $todayTime)
            ->count();

        return [
            'new_bookings_today' => $newBookingsToday,
            'payments_today' => $paymentsToday,
            'awaiting_approval' => $awaitingApproval,
            'customers_waiting' => $customersWaiting,
        ];
    }

    /**
     * Get weekly bookings in legacy format for AnalyticsController compatibility.
     * Maps to the old controller's getWeeklyBookings() method.
     */
    private function getWeeklyBookingsLegacy(Collection $bookings): array
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        return collect($days)->map(function ($day) use ($bookings) {
            return [
                'day' => $day,
                'bookings' => $bookings->filter(function ($booking) use ($day) {
                    return Carbon::parse($booking->date)->format('D') === $day;
                })->count(),
            ];
        })->toArray();
    }
}
