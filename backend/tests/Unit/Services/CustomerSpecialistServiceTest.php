<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Provider;
use App\Models\Booking;
use App\Models\Service;
use App\Models\CustomerSpecialist;
use App\Services\CustomerSpecialistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSpecialistServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSpecialistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerSpecialistService();
    }

    public function test_track_relationship_creates_new_relationship()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $specialist = Specialist::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'price' => 50000]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        $relationship = $this->service->trackRelationshipFromBooking($booking);

        $this->assertInstanceOf(CustomerSpecialist::class, $relationship);
        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 1,
            'total_spent' => 50000,
            'first_booking_id' => $booking->id,
            'last_booking_id' => $booking->id,
        ]);
    }

    public function test_track_relationship_updates_existing_relationship()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $specialist = Specialist::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'price' => 50000]);

        // Create initial relationship
        $existingRelationship = CustomerSpecialist::create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 3,
            'total_spent' => 150000,
            'first_booking_id' => 'old-booking-id',
            'last_booking_id' => 'old-booking-id',
            'relationship_origin' => 'salon',
            'acquisition_source' => 'booking',
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        $relationship = $this->service->trackRelationshipFromBooking($booking);

        $this->assertInstanceOf(CustomerSpecialist::class, $relationship);
        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 4, // 3 + 1
            'total_spent' => 200000, // 150000 + 50000
            'first_booking_id' => 'old-booking-id', // Should not change
            'last_booking_id' => $booking->id, // Should update
        ]);
    }

    public function test_track_relationship_with_composite_key_safety()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $specialist = Specialist::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'price' => 30000]);

        // Create relationship using direct DB insert to simulate composite key scenario
        \DB::table('customer_specialist')->insert([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 1,
            'total_spent' => 30000,
            'first_booking_id' => 'initial-booking',
            'last_booking_id' => 'initial-booking',
            'relationship_origin' => 'salon',
            'acquisition_source' => 'booking',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        // This should not throw TypeError even with composite key
        $relationship = $this->service->trackRelationshipFromBooking($booking);

        $this->assertInstanceOf(CustomerSpecialist::class, $relationship);
        $this->assertEquals(2, $relationship->total_bookings);
        $this->assertEquals(60000, $relationship->total_spent);
    }

    public function test_track_relationship_throws_exception_without_specialist()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => null,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking must have specialist_id, customer_id, and provider_id');

        $this->service->trackRelationshipFromBooking($booking);
    }

    public function test_track_relationship_preserves_provider_isolation()
    {
        $provider1 = Provider::factory()->create();
        $provider2 = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $specialist = Specialist::factory()->create();
        $service1 = Service::factory()->create(['provider_id' => $provider1->id, 'price' => 50000]);
        $service2 = Service::factory()->create(['provider_id' => $provider2->id, 'price' => 60000]);

        // Create relationship for provider1
        $booking1 = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider1->id,
            'service_id' => $service1->id,
            'status' => 'confirmed',
        ]);

        $this->service->trackRelationshipFromBooking($booking1);

        // Create booking for provider2
        $booking2 = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider2->id,
            'service_id' => $service2->id,
            'status' => 'confirmed',
        ]);

        $this->service->trackRelationshipFromBooking($booking2);

        // Verify both relationships exist independently
        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider1->id,
            'total_bookings' => 1,
        ]);

        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider2->id,
            'total_bookings' => 1,
        ]);
    }

    public function test_track_relationship_updates_timestamps()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $specialist = Specialist::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'price' => 50000]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        $relationship = $this->service->trackRelationshipFromBooking($booking);

        $this->assertNotNull($relationship->last_interaction_at);
        $this->assertNotNull($relationship->last_seen_at);
        $this->assertNotNull($relationship->updated_at);
    }
}
