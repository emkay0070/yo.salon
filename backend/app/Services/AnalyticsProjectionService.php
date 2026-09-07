<?php

namespace App\Services;

use App\Models\Salon;
use App\Services\Finance\FinanceAnalyticsService;
use App\Services\OperationalAnalyticsService;
use App\Services\Intelligence\IntelligenceEngine;

/**
 * Analytics Projection Service
 * 
 * Composes canonical facts from Finance, Operations, and Intelligence domains
 * into a legacy-compatible response shape for /v1/analytics endpoint.
 * 
 * This is a PROJECTION LAYER - no calculations, only composition.
 */
class AnalyticsProjectionService
{
    protected FinanceAnalyticsService $financeAnalyticsService;
    protected OperationalAnalyticsService $operationalAnalyticsService;
    protected IntelligenceEngine $intelligenceEngine;

    public function __construct(
        FinanceAnalyticsService $financeAnalyticsService,
        OperationalAnalyticsService $operationalAnalyticsService,
        IntelligenceEngine $intelligenceEngine
    ) {
        $this->financeAnalyticsService = $financeAnalyticsService;
        $this->operationalAnalyticsService = $operationalAnalyticsService;
        $this->intelligenceEngine = $intelligenceEngine;
    }

    /**
     * Compose the legacy-compatible analytics response from canonical facts.
     * 
     * @param Salon $salon
     * @return array Legacy-compatible response shape
     */
    public function compose(Salon $salon): array
    {
        // Fetch canonical facts from each domain
        $financeMetrics = $this->financeAnalyticsService->getSalonMetrics($salon);
        $todayMetrics = $this->financeAnalyticsService->getTodayMetrics($salon);
        $revenueTrend = $this->financeAnalyticsService->getRevenueTrend($salon);
        $operationalFacts = $this->operationalAnalyticsService->getOperationalFacts($salon);
        $intelligenceDto = $this->intelligenceEngine->generate($salon);

        // Compose legacy-compatible response
        return [
            // Finance Facts
            'total_bookings' => $operationalFacts['total_bookings'],
            'total_revenue' => $financeMetrics['total_gross_revenue'], // backward compatibility
            'total_gross_revenue' => $financeMetrics['total_gross_revenue'],
            'total_net_revenue' => $financeMetrics['total_net_revenue'],
            'status_counts' => $operationalFacts['status_counts'],
            'today_bookings' => $operationalFacts['today']['new_bookings_today'],
            'today_revenue' => $todayMetrics['today_gross_revenue'], // backward compatibility
            'today_gross_revenue' => $todayMetrics['today_gross_revenue'],
            'today_net_revenue' => $todayMetrics['today_net_revenue'],
            'revenue_trend' => $revenueTrend,

            // Operational Facts (mapped to legacy shape)
            'service_stats' => $this->mapServiceStats($financeMetrics['revenue_by_service'] ?? []),
            'staff_stats' => $this->mapStaffStats($financeMetrics['revenue_by_specialist'] ?? []),
            'weekly_bookings' => $operationalFacts['weekly_bookings'],

            // Intelligence Facts
            'insights' => $this->mapInsights($intelligenceDto['signals'] ?? []),
            'executive_summary' => $intelligenceDto['briefing']['narrative'] ?? '',
            
            // Note: basic_insights removed - duplicate of intelligence data
        ];
    }

    /**
     * Map FinanceAnalyticsService revenue_by_service to legacy service_stats shape.
     */
    private function mapServiceStats(array $revenueByService): array
    {
        return collect($revenueByService)->map(function ($item) {
            return [
                'service_id' => $item['service_id'] ?? null,
                'service_name' => $item['service_name'] ?? 'Unknown',
                'count' => $item['booking_count'] ?? 0,
                'revenue' => $item['revenue'] ?? 0, // Now from Ledger, not catalog price
            ];
        })->values()->toArray();
    }

    /**
     * Map FinanceAnalyticsService revenue_by_specialist to legacy staff_stats shape.
     */
    private function mapStaffStats(array $revenueBySpecialist): array
    {
        return collect($revenueBySpecialist)->map(function ($item) {
            return [
                'staff_id' => $item['specialist_id'] ?? null, // Maps specialist_id to staff_id for legacy compatibility
                'staff_name' => $item['specialist_name'] ?? 'Unknown',
                'count' => $item['booking_count'] ?? 0,
                'revenue' => $item['revenue'] ?? 0, // Now from Ledger, not catalog price
            ];
        })->values()->toArray();
    }

    /**
     * Map IntelligenceEngine signals to legacy insights shape.
     */
    private function mapInsights(array $signals): array
    {
        return collect($signals)->map(function ($signal) {
            return [
                'title' => $signal['title'] ?? '',
                'description' => $signal['description'] ?? '',
                'type' => $signal['type'] ?? 'info',
                'action_text' => $signal['recommended_action'] ?? '',
                'is_predictive' => $signal['is_predictive'] ?? false,
            ];
        })->values()->toArray();
    }
}
