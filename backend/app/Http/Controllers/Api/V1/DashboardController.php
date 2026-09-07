<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Salon;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\OperationalAnalyticsService;
use App\Services\Finance\FinanceAnalyticsService;

class DashboardController extends Controller
{
    protected OperationalAnalyticsService $operationalAnalyticsService;
    protected FinanceAnalyticsService $financeAnalyticsService;

    public function __construct(
        OperationalAnalyticsService $operationalAnalyticsService,
        FinanceAnalyticsService $financeAnalyticsService
    ) {
        $this->operationalAnalyticsService = $operationalAnalyticsService;
        $this->financeAnalyticsService = $financeAnalyticsService;
    }

    public function liveStats(): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['message' => 'No salon associated with your account'], 403);
        }

        $cacheKey = "dashboard_stats_{$salon->id}";
        $today = Carbon::today()->toDateString();

        // Try to get from cache first (reduced to 30s for live polling)
        $stats = Cache::get($cacheKey);

        // If cache doesn't exist or is from a different day, recalculate
        if (!$stats || ($stats['date'] !== $today)) {
            // Get canonical operational facts
            $operationalFacts = $this->operationalAnalyticsService->getOperationalFacts($salon);
            
            // Get canonical financial facts for today
            $financialFacts = $this->financeAnalyticsService->getTodayMetrics($salon);

            $stats = [
                'date' => $today,
                'new_bookings_today' => $operationalFacts['today']['new_bookings_today'],
                'payments_today' => $operationalFacts['today']['payments_today'],
                'awaiting_approval' => $operationalFacts['today']['awaiting_approval'],
                'customers_waiting' => $operationalFacts['today']['customers_waiting'],
                'revenue_today' => $financialFacts['today_net_revenue'], // Canonical from Ledger
            ];

            // Cache for 30 seconds (matches frontend polling frequency)
            Cache::put($cacheKey, $stats, 30);
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
