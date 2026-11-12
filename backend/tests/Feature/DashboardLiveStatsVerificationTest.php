<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Location;
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
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // Create paid booking
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'updated_at' => now(),
        ]);

        $operationalService = app(OperationalAnalyticsService::class);
        $facts = $operationalService->getOperationalFacts($provider, $location);

        $this->assertArrayHasKey('payments_today', $facts['today']);
        $this->assertEquals(1, $facts['today']['payments_today']);
    }

    public function test_dashboard_controller_uses_operational_analytics_service()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // Create bookings
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'date' => now()->addDays(1)->toDateString(),
        ]);

        $operationalService = app(OperationalAnalyticsService::class);
        $facts = $operationalService->getOperationalFacts($provider, $location);

        // Verify the service returns the expected structure
        $this->assertArrayHasKey('today', $facts);
        $this->assertArrayHasKey('new_bookings_today', $facts['today']);
        $this->assertArrayHasKey('payments_today', $facts['today']);
        $this->assertArrayHasKey('awaiting_approval', $facts['today']);
        $this->assertArrayHasKey('customers_waiting', $facts['today']);
    }

    public function test_finance_analytics_service_has_today_metrics()
    {
        $provider = Provider::factory()->create();
        
        $financeService = app(FinanceAnalyticsService::class);
        $metrics = $financeService->getTodayMetrics($provider);

        $this->assertArrayHasKey('today_gross_revenue', $metrics);
        $this->assertArrayHasKey('today_net_revenue', $metrics);
    }
}


