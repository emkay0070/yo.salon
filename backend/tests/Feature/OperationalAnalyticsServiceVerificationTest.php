<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Location;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Specialist;
use App\Services\OperationalAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 4: Operational Analytics Verification
 * 
 * This test suite verifies that OperationalAnalyticsService correctly
 * provides canonical operational facts for the Intelligence Engine.
 * 
 * Principle: This service owns booking behavior facts, NOT revenue.
 */
class OperationalAnalyticsServiceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected OperationalAnalyticsService $operationalAnalyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operationalAnalyticsService = app(OperationalAnalyticsService::class);
    }

    public function test_it_returns_empty_facts_with_no_bookings()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $this->assertEquals(0, $facts['totals']['total_bookings']);
        $this->assertEquals(0, $facts['totals']['completed_bookings']);
        $this->assertEquals(0, $facts['totals']['cancelled_bookings']);
        $this->assertEquals(0, $facts['totals']['no_show_bookings']);
        $this->assertEquals(0, $facts['totals']['completion_rate']);
        $this->assertEquals(0, $facts['totals']['cancellation_rate']);
        $this->assertEquals(0, $facts['totals']['no_show_rate']);
    }

    public function test_it_correctly_counts_bookings_by_status()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'no_show',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $this->assertEquals(5, $facts['totals']['total_bookings']);
        $this->assertEquals(1, $facts['by_status']['pending']);
        $this->assertEquals(1, $facts['by_status']['confirmed']);
        $this->assertEquals(1, $facts['by_status']['completed']);
        $this->assertEquals(1, $facts['by_status']['cancelled']);
        $this->assertEquals(1, $facts['by_status']['no_show']);
    }

    public function test_it_calculates_completion_rate_correctly()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // 3 confirmed bookings, 2 completed
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        // Completion rate = completed / confirmed = 2/3 = 66.7%
        $this->assertEquals(66.7, $facts['totals']['completion_rate']);
    }

    public function test_it_calculates_cancellation_rate_correctly()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // 10 total bookings, 3 cancelled
        for ($i = 0; $i < 7; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'confirmed',
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'cancelled',
            ]);
        }

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        // Cancellation rate = cancelled / total = 3/10 = 30%
        $this->assertEquals(30.0, $facts['totals']['cancellation_rate']);
    }

    public function test_it_calculates_no_show_rate_correctly()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // 5 confirmed bookings, 1 no-show
        for ($i = 0; $i < 4; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'confirmed',
            ]);
        }

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'no_show',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        // No-show rate = no_show / confirmed = 1/5 = 20%
        $this->assertEquals(20.0, $facts['totals']['no_show_rate']);
    }

    public function test_it_correctly_attributes_bookings_by_service()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        
        $service1 = Service::factory()->create(['provider_id' => $provider->id, 'name' => 'Haircut']);
        $service2 = Service::factory()->create(['provider_id' => $provider->id, 'name' => 'Manicure']);

        // 3 bookings for service1
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer->id,
                'service_id' => $service1->id,
                'status' => 'completed',
            ]);
        }

        // 2 bookings for service2
        for ($i = 0; $i < 2; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer->id,
                'service_id' => $service2->id,
                'status' => 'completed',
            ]);
        }

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $this->assertCount(2, $facts['by_service']);
        
        $service1Stats = collect($facts['by_service'])->firstWhere('service_id', $service1->id);
        $service2Stats = collect($facts['by_service'])->firstWhere('service_id', $service2->id);

        $this->assertEquals(3, $service1Stats['booking_count']);
        $this->assertEquals('Haircut', $service1Stats['service_name']);
        
        $this->assertEquals(2, $service2Stats['booking_count']);
        $this->assertEquals('Manicure', $service2Stats['service_name']);
    }

    public function test_it_correctly_identifies_repeat_customers()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // Customer1 has 3 bookings (repeat)
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'location_id' => $location->id,
                'provider_id' => $provider->id,
                'customer_id' => $customer1->id,
                'service_id' => $service->id,
                'status' => 'completed',
            ]);
        }

        // Customer2 has 1 booking (not repeat)
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer2->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $customer1Stats = collect($facts['by_customer'])->firstWhere('customer_id', $customer1->id);
        $customer2Stats = collect($facts['by_customer'])->firstWhere('customer_id', $customer2->id);

        $this->assertTrue($customer1Stats['is_repeat_customer']);
        $this->assertFalse($customer2Stats['is_repeat_customer']);
    }

    public function test_it_generates_weekly_bookings_pattern()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // Create bookings on different days
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(0)->toDateString(), // Monday
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(2)->toDateString(), // Wednesday
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(4)->toDateString(), // Friday
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Monday']);
        $this->assertEquals(0, $facts['temporal']['weekly_bookings']['Tuesday']);
        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Wednesday']);
        $this->assertEquals(0, $facts['temporal']['weekly_bookings']['Thursday']);
        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Friday']);
    }

    public function test_it_calculates_today_stats_correctly()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        // Create 1 pending booking with future date
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'date' => now()->addDay()->toDateString(),
        ]);

        // Create 1 confirmed booking for today with future time
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
            'date' => now()->toDateString(),
            'time' => now()->addHour()->format('H:i'),
        ]);

        // Create 1 booking with payment_status='paid' updated today
        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'date' => now()->subDay()->toDateString(),
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        $this->assertEquals(1, $facts['today']['payments_today']); // Only paid booking
        $this->assertEquals(1, $facts['today']['awaiting_approval']); // Only pending booking
        $this->assertEquals(1, $facts['today']['customers_waiting']); // Only confirmed booking for today
    }

    public function test_it_does_not_include_revenue_in_operational_facts()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'price' => 100000]);

        Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        // Verify no revenue fields exist
        $this->assertArrayNotHasKey('revenue', $facts['totals']);
        $this->assertArrayNotHasKey('revenue', $facts['by_service'][0]);
        $this->assertArrayNotHasKey('revenue', $facts['by_specialist']);
        
        // Verify service stats don't include revenue
        $this->assertArrayNotHasKey('revenue', $facts['by_service'][0]);
        $this->assertArrayHasKey('booking_count', $facts['by_service'][0]);
    }
}
