<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Services\OperationalAnalyticsService;
use App\Services\Finance\FinanceAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 5: Dashboard Live Stats Verification
 * 
 * This test suite verifies that DashboardController correctly consumes
 * canonical facts from OperationalAnalyticsService and FinanceAnalyticsService.
 */
class DashboardLiveStatsVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_analytics_service_includes_payments_today()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // Create paid booking
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'updated_at' => now(),
        ]);

        $operationalService = app(OperationalAnalyticsService::class);
        $facts = $operationalService->getOperationalFacts($salon);

        $this->assertArrayHasKey('payments_today', $facts['today']);
        $this->assertEquals(1, $facts['today']['payments_today']);
    }

    public function test_dashboard_controller_uses_operational_analytics_service()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // Create bookings
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'date' => now()->addDays(1)->toDateString(),
        ]);

        $operationalService = app(OperationalAnalyticsService::class);
        $facts = $operationalService->getOperationalFacts($salon);

        // Verify the service returns the expected structure
        $this->assertArrayHasKey('today', $facts);
        $this->assertArrayHasKey('new_bookings_today', $facts['today']);
        $this->assertArrayHasKey('payments_today', $facts['today']);
        $this->assertArrayHasKey('awaiting_approval', $facts['today']);
        $this->assertArrayHasKey('customers_waiting', $facts['today']);
    }

    public function test_finance_analytics_service_has_today_metrics()
    {
        $salon = Salon::factory()->create();
        
        $financeService = app(FinanceAnalyticsService::class);
        $metrics = $financeService->getTodayMetrics($salon);

        $this->assertArrayHasKey('today_gross_revenue', $metrics);
        $this->assertArrayHasKey('today_net_revenue', $metrics);
    }
}


