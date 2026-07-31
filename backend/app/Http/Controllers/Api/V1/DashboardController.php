<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use\Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function liveStats(): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) {
            return response()->json(['message' => 'No salon associated with your account'], 403);
        }

        $today = Carbon::today();

        // New bookings today
        $newBookingsToday = Booking::where('salon_id', $salonId)
            ->whereDate('created_at', $today)
            ->count();

        // Payments today (bookings with paid status today)
        $paymentsToday = Booking::where('salon_id', $salonId)
            ->where('payment_status', 'paid')
            ->whereDate('updated_at', $today)
            ->count();

        // Awaiting approval (pending bookings)
        $awaitingApproval = Booking::where('salon_id', $salonId)
            ->where('status', 'pending')
            ->whereDate('date', '>=', $today)
            ->count();

        // Customers waiting (confirmed bookings for today that haven't started)
        $customersWaiting = Booking::where('salon_id', $salonId)
            ->where('status', 'confirmed')
            ->whereDate('date', $today)
            ->where('time', '>=', $today->format('H:i:s'))
            ->count();

        // Revenue today (sum of booking service prices for paid bookings today)
        $revenueToday = Booking::where('salon_id', $salonId)
            ->where('payment_status', 'paid')
            ->whereDate('updated_at', $today)
            ->with('services')
            ->get()
            ->sum(function ($booking) {
                return $booking->services->sum('price');
            });

        return response()->json([
            'new_bookings_today' => $newBookingsToday,
            'payments_today' => $paymentsToday,
            'awaiting_approval' => $awaitingApproval,
            'customers_waiting' => $customersWaiting,
            'revenue_today' => $revenueToday,
        ]);
    }
}
