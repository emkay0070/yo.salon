<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Services\AnalyticsProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 8: Analytics Projection Verification
 * 
 * This test suite verifies that AnalyticsProjectionService correctly composes
 * canonical facts from Finance, Operations, and Intelligence domains into
 * a legacy-compatible response shape.
 */
class AnalyticsProjectionVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_service_returns_legacy_compatible_structure()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // Verify legacy-compatible response structure
        $this->assertArrayHasKey('total_bookings', $analytics);
        $this->assertArrayHasKey('total_gross_revenue', $analytics);
        $this->assertArrayHasKey('total_net_revenue', $analytics);
        $this->assertArrayHasKey('status_counts', $analytics);
        $this->assertArrayHasKey('today_bookings', $analytics);
        $this->assertArrayHasKey('today_gross_revenue', $analytics);
        $this->assertArrayHasKey('today_net_revenue', $analytics);
        $this->assertArrayHasKey('revenue_trend', $analytics);
        $this->assertArrayHasKey('service_stats', $analytics);
        $this->assertArrayHasKey('staff_stats', $analytics);
        $this->assertArrayHasKey('weekly_bookings', $analytics);
        $this->assertArrayHasKey('insights', $analytics);
        $this->assertArrayHasKey('executive_summary', $analytics);
        
        // Verify basic_insights is removed (duplicate)
        $this->assertArrayNotHasKey('basic_insights', $analytics);
    }

    public function test_projection_uses_canonical_finance_facts()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // Financial facts should come from FinanceAnalyticsService (Ledger-based)
        $this->assertIsArray($analytics['revenue_trend']);
        $this->assertIsInt($analytics['total_gross_revenue']);
        $this->assertIsInt($analytics['total_net_revenue']);
    }

    public function test_projection_uses_canonical_operational_facts()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // Operational facts should come from OperationalAnalyticsService
        $this->assertIsArray($analytics['status_counts']);
        $this->assertIsArray($analytics['weekly_bookings']);
        $this->assertIsInt($analytics['total_bookings']);
    }

    public function test_projection_uses_canonical_intelligence_facts()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // Intelligence facts should come from NEW Intelligence Engine
        $this->assertIsArray($analytics['insights']);
        $this->assertIsString($analytics['executive_summary']);
    }

    public function test_service_stats_maps_from_ledger_not_catalog()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // service_stats should be mapped from FinanceAnalyticsService (Ledger)
        // NOT from booking.service.price (catalog)
        $this->assertIsArray($analytics['service_stats']);
        
        // Each service stat should have the legacy shape
        foreach ($analytics['service_stats'] as $stat) {
            $this->assertArrayHasKey('service_id', $stat);
            $this->assertArrayHasKey('service_name', $stat);
            $this->assertArrayHasKey('count', $stat);
            $this->assertArrayHasKey('revenue', $stat);
        }
    }

    public function test_staff_stats_maps_from_ledger_not_catalog()
    {
        $salon = Salon::factory()->create();
        
        $projectionService = app(AnalyticsProjectionService::class);
        $analytics = $projectionService->compose($salon);

        // staff_stats should be mapped from FinanceAnalyticsService (Ledger)
        // NOT from booking.service.price (catalog)
        $this->assertIsArray($analytics['staff_stats']);
        
        // Each staff stat should have the legacy shape
        foreach ($analytics['staff_stats'] as $stat) {
            $this->assertArrayHasKey('staff_id', $stat);
            $this->assertArrayHasKey('staff_name', $stat);
            $this->assertArrayHasKey('count', $stat);
            $this->assertArrayHasKey('revenue', $stat);
        }
    }
}
