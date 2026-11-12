<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for the booking data persistence issue
 * 
 * This test verifies that the missing BookingConfigurationService dependency
 * does not cause customer-provider relationships to be lost during booking creation.
 */
class BookingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /**
     * Test that BookingService has all required dependencies
     * This ensures the missing configService defect is fixed
     */
    public function test_booking_service_has_config_service_dependency()
    {
        $this->assertObjectHasProperty('configService', $this->bookingService);
    }

    /**
     * Test that determineInitialBookingStatus works correctly
     * This ensures the configService dependency is functional
     */
    public function test_determine_initial_booking_status_works()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->for($provider)->create();
        $service = Service::factory()->for($provider)->create();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->bookingService);
        $method = $reflection->getMethod('determineInitialBookingStatus');
        $method->setAccessible(true);

        $status = $method->invoke(
            $this->bookingService,
            $provider->id,
            $location->id,
            $service->id,
            false
        );

        // Should return either 'pending' or 'confirmed'
        $this->assertIsString($status);
        $this->assertContains($status, ['pending', 'confirmed']);
    }

    /**
     * Test that booking creates customer-provider relationship
     * This is the core regression test for the missing relationships issue
     */
    public function test_booking_creates_customer_provider_relationship()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->for($provider)->create();
        $service = Service::factory()->for($provider)->create(['duration' => 60]);

        $bookingData = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_phone' => '1234567890',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'time' => '10:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ];

        $booking = $this->bookingService->createGuestBooking($bookingData);

        // Verify booking was created
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'provider_id' => $provider->id,
            'customer_id' => $booking->customer_id,
        ]);

        // Verify customer-provider relationship exists
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $booking->customer_id,
            'provider_id' => $provider->id,
        ]);
    }

    /**
     * Test that booking for provider A cannot create relationship for provider B
     * This ensures provider isolation is maintained
     */
    public function test_booking_for_provider_a_cannot_create_relationship_for_provider_b()
    {
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $locationA = Location::factory()->for($providerA)->create();
        $serviceA = Service::factory()->for($providerA)->create(['duration' => 60]);

        $bookingData = [
            'provider_id' => $providerA->id,
            'location_id' => $locationA->id,
            'customer_phone' => '1234567890',
            'customer_name' => 'Test Customer',
            'service_id' => $serviceA->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'time' => '10:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ];

        $booking = $this->bookingService->createGuestBooking($bookingData);

        // Verify relationship exists for provider A
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $booking->customer_id,
            'provider_id' => $providerA->id,
        ]);

        // Verify relationship does NOT exist for provider B
        $this->assertDatabaseMissing('customer_providers', [
            'customer_id' => $booking->customer_id,
            'provider_id' => $providerB->id,
        ]);
    }

    /**
     * Test that customer-provider relationship creation is idempotent
     * This ensures multiple bookings for the same customer don't create duplicates
     */
    public function test_customer_provider_relationship_is_idempotent()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->for($provider)->create();
        $service = Service::factory()->for($provider)->create(['duration' => 60]);

        $bookingData = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_phone' => '1234567890',
            'customer_name' => 'Test Customer',
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'time' => '10:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ];

        // Create first booking
        $booking1 = $this->bookingService->createGuestBooking($bookingData);

        // Create second booking for same customer
        $bookingData['date'] = Carbon::now()->addDays(6)->toDateString();
        $booking2 = $this->bookingService->createGuestBooking($bookingData);

        // Verify both bookings exist
        $this->assertDatabaseHas('bookings', ['id' => $booking1->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking2->id]);

        // Verify only one customer-provider relationship exists
        $relationshipCount = DB::table('customer_providers')
            ->where('customer_id', $booking1->customer_id)
            ->where('provider_id', $provider->id)
            ->count();

        $this->assertEquals(1, $relationshipCount);
    }

    /**
     * Test that transaction rolls back on exception
     * This ensures that if determineInitialBookingStatus fails, nothing is committed
     */
    public function test_transaction_rolls_back_on_exception()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->for($provider)->create();
        $service = Service::factory()->for($provider)->create(['duration' => 60]);

        // Create a customer first
        $customer = Customer::factory()->create();

        $bookingData = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'time' => '10:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ];

        // Manually inject a failure by using an invalid service_id
        $bookingData['service_id'] = 'invalid-uuid';

        $this->expectException(\Exception::class);

        try {
            $this->bookingService->createGuestBooking($bookingData);
        } catch (\Exception $e) {
            // Verify no booking was created
            $this->assertDatabaseMissing('bookings', [
                'customer_id' => $customer->id,
                'provider_id' => $provider->id,
            ]);

            // Verify no customer-provider relationship was created
            $this->assertDatabaseMissing('customer_providers', [
                'customer_id' => $customer->id,
                'provider_id' => $provider->id,
            ]);

            throw $e;
        }
    }
}
