<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function liveStats(): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) {
            return response()->json(['message' => 'No salon associated with your account'], 403);
        }

        $cacheKey = "dashboard_stats_{$salonId}";
        $today = Carbon::today()->toDateString();

        // Try to get from cache first
        $stats = Cache::get($cacheKey);

        // If cache doesn't exist or is from a different day, recalculate
        if (!$stats || ($stats['date'] !== $today)) {
            $todayDate = Carbon::today();

            // New bookings today
            $newBookingsToday = Booking::where('salon_id', $salonId)
                ->whereDate('created_at', $todayDate)
                ->count();

            // Payments today (bookings with paid status today)
            $paymentsToday = Booking::where('salon_id', $salonId)
                ->where('payment_status', 'paid')
                ->whereDate('updated_at', $todayDate)
                ->count();

            // Awaiting approval (pending bookings)
            $awaitingApproval = Booking::where('salon_id', $salonId)
                ->where('status', 'pending')
                ->whereDate('date', '>=', $todayDate)
                ->count();

            // Customers waiting (confirmed bookings for today that haven't started)
            $customersWaiting = Booking::where('salon_id', $salonId)
                ->where('status', 'confirmed')
                ->whereDate('date', $todayDate)
                ->where('time', '>=', $todayDate->format('H:i:s'))
                ->count();

            // Revenue today (sum of booking service prices for paid bookings today)
            $revenueToday = Booking::where('salon_id', $salonId)
                ->where('payment_status', 'paid')
                ->whereDate('updated_at', $todayDate)
                ->with('services')
                ->get()
                ->sum(function ($booking) {
                    return $booking->services->sum('price');
                });

            $stats = [
                'date' => $today,
                'new_bookings_today' => $newBookingsToday,
                'payments_today' => $paymentsToday,
                'awaiting_approval' => $awaitingApproval,
                'customers_waiting' => $customersWaiting,
                'revenue_today' => $revenueToday,
            ];

            // Cache for 5 minutes (300 seconds)
            Cache::put($cacheKey, $stats, 300);
        }

        return response()->json($stats);
    }

    /**
     * Invalidate dashboard stats cache (call this when events occur)
     */
    public function invalidateStatsCache(string $salonId): void
    {
        Cache::forget("dashboard_stats_{$salonId}");
    }
}
