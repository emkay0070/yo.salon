<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\OperationalAnalyticsService;
use App\Services\Finance\FinanceAnalyticsService;
use App\Services\BookingService;

class DashboardController extends Controller
{
    protected OperationalAnalyticsService $operationalAnalyticsService;
    protected FinanceAnalyticsService $financeAnalyticsService;
    protected BookingService $bookingService;

    public function __construct(
        OperationalAnalyticsService $operationalAnalyticsService,
        FinanceAnalyticsService $financeAnalyticsService,
        BookingService $bookingService
    ) {
        $this->operationalAnalyticsService = $operationalAnalyticsService;
        $this->financeAnalyticsService = $financeAnalyticsService;
        $this->bookingService = $bookingService;
    }

    public function liveStats(): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) {
            return response()->json(['message' => 'No provider associated with your account'], 403);
        }

        // Process auto-approvals during polling (idempotent, safe to call frequently)
        $autoApprovalResult = $this->bookingService->processAutoApprovals($provider->id);

        $cacheKey = "dashboard_stats_{$provider->id}";
        $today = Carbon::today()->toDateString();

        // Try to get from cache first (reduced to 30s for live polling)
        $stats = Cache::get($cacheKey);

        // If cache doesn't exist or is from a different day, recalculate
        if (!$stats || ($stats['date'] !== $today)) {
            // Get canonical operational facts
            $operationalFacts = $this->operationalAnalyticsService->getOperationalFacts($provider);
            
            // Get canonical financial facts for today
            $financialFacts = $this->financeAnalyticsService->getTodayMetrics($provider);

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

        // Include auto-approval stats in response for transparency
        $stats['auto_approvals'] = $autoApprovalResult;

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
