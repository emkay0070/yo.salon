<?php

namespace Tests\Feature;

use App\Models\Salon;
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
        $salon = Salon::factory()->create();

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

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
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'no_show',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        $this->assertEquals(5, $facts['totals']['total_bookings']);
        $this->assertEquals(1, $facts['by_status']['pending']);
        $this->assertEquals(1, $facts['by_status']['confirmed']);
        $this->assertEquals(1, $facts['by_status']['completed']);
        $this->assertEquals(1, $facts['by_status']['cancelled']);
        $this->assertEquals(1, $facts['by_status']['no_show']);
    }

    public function test_it_calculates_completion_rate_correctly()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // 3 confirmed bookings, 2 completed
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        // Completion rate = completed / confirmed = 2/3 = 66.7%
        $this->assertEquals(66.7, $facts['totals']['completion_rate']);
    }

    public function test_it_calculates_cancellation_rate_correctly()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // 10 total bookings, 3 cancelled
        for ($i = 0; $i < 7; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'completed',
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'cancelled',
            ]);
        }

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        // Cancellation rate = cancelled / total = 3/10 = 30%
        $this->assertEquals(30.0, $facts['totals']['cancellation_rate']);
    }

    public function test_it_calculates_no_show_rate_correctly()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // 5 confirmed bookings, 1 no-show
        for ($i = 0; $i < 4; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'status' => 'confirmed',
            ]);
        }

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'no_show',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        // No-show rate = no_show / confirmed = 1/5 = 20%
        $this->assertEquals(20.0, $facts['totals']['no_show_rate']);
    }

    public function test_it_correctly_attributes_bookings_by_service()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $service1 = Service::factory()->create(['provider_id' => $salon->provider_id, 'name' => 'Haircut']);
        $service2 = Service::factory()->create(['provider_id' => $salon->provider_id, 'name' => 'Manicure']);

        // 3 bookings for service1
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'service_id' => $service1->id,
                'status' => 'completed',
            ]);
        }

        // 2 bookings for service2
        for ($i = 0; $i < 2; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'service_id' => $service2->id,
                'status' => 'completed',
            ]);
        }

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

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
        $salon = Salon::factory()->create();
        
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // Customer1 has 3 bookings (repeat)
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'salon_id' => $salon->id,
                'customer_id' => $customer1->id,
                'service_id' => $service->id,
                'status' => 'completed',
            ]);
        }

        // Customer2 has 1 booking (not repeat)
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer2->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        $customer1Stats = collect($facts['by_customer'])->firstWhere('customer_id', $customer1->id);
        $customer2Stats = collect($facts['by_customer'])->firstWhere('customer_id', $customer2->id);

        $this->assertTrue($customer1Stats['is_repeat_customer']);
        $this->assertFalse($customer2Stats['is_repeat_customer']);
    }

    public function test_it_generates_weekly_bookings_pattern()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // Create bookings on different days
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(0)->toDateString(), // Monday
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(2)->toDateString(), // Wednesday
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->startOfWeek()->addDays(4)->toDateString(), // Friday
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Monday']);
        $this->assertEquals(0, $facts['temporal']['weekly_bookings']['Tuesday']);
        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Wednesday']);
        $this->assertEquals(0, $facts['temporal']['weekly_bookings']['Thursday']);
        $this->assertEquals(1, $facts['temporal']['weekly_bookings']['Friday']);
    }

    public function test_it_calculates_today_stats_correctly()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id]);

        // Create 1 pending booking with future date
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'date' => now()->addDays(1)->toDateString(),
        ]);

        // Create 1 confirmed booking for today with future time
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
            'date' => now()->toDateString(),
            'time' => now()->addHours(2)->format('H:i:s'),
        ]);

        // Create 1 booking with payment_status='paid' updated today
        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'updated_at' => now(),
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        // All bookings were created today by default
        $this->assertEquals(3, $facts['today']['new_bookings_today']);
        $this->assertEquals(1, $facts['today']['payments_today']); // Only paid booking
        $this->assertEquals(1, $facts['today']['awaiting_approval']); // Only pending booking
        $this->assertEquals(1, $facts['today']['customers_waiting']); // Only confirmed booking for today
    }

    public function test_it_does_not_include_revenue_in_operational_facts()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id, 'price' => 100000]);

        Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $facts = $this->operationalAnalyticsService->getOperationalFacts($salon);

        // Verify no revenue fields exist
        $this->assertArrayNotHasKey('revenue', $facts['totals']);
        $this->assertArrayNotHasKey('revenue', $facts['by_service'][0]);
        $this->assertArrayNotHasKey('revenue', $facts['by_specialist']);
        
        // Verify service stats don't include revenue
        $this->assertArrayNotHasKey('revenue', $facts['by_service'][0]);
        $this->assertArrayHasKey('booking_count', $facts['by_service'][0]);
    }
}
