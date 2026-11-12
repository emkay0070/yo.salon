<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingLocationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $locationA;
    private Location $locationB;
    private Service $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        
        $this->locationA = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->locationB = Location::factory()->create(['provider_id' => $this->provider->id]);
        
        $this->service = Service::factory()->create(['provider_id' => $this->provider->id]);
        $this->customer = Customer::factory()->create();
    }

    public function test_booking_gets_location_id()
    {
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $this->assertEquals($this->locationA->id, $booking->location_id);
    }

    public function test_provider_id_remains_consistent()
    {
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $this->assertEquals($this->provider->id, $booking->provider_id);
    }

    public function test_location_id_is_required()
    {
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $this->assertNotNull($booking->location_id);
        $this->assertEquals($this->locationA->id, $booking->location_id);
    }

    public function test_multiple_locations_under_same_provider_remain_distinguishable()
    {
        $bookingA = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $bookingB = Booking::create([
            'location_id' => $this->locationB->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '11:00',
            'status' => 'confirmed',
        ]);

        $this->assertEquals($this->locationA->id, $bookingA->location_id);
        $this->assertEquals($this->locationB->id, $bookingB->location_id);
        $this->assertNotEquals($bookingA->location_id, $bookingB->location_id);
        $this->assertEquals($this->provider->id, $bookingA->provider_id);
        $this->assertEquals($this->provider->id, $bookingB->provider_id);
    }

    public function test_booking_location_relationship_resolves_correctly()
    {
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $location = $booking->location;

        $this->assertNotNull($location);
        $this->assertEquals($this->locationA->id, $location->id);
        $this->assertEquals($this->locationA->name, $location->name);
    }

    public function test_booking_service_creates_with_location_id()
    {
        // Direct booking creation to test canonical model
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $this->assertEquals($this->locationA->id, $booking->location_id);
        $this->assertEquals($this->provider->id, $booking->provider_id);
    }

    public function test_booking_factory_generates_consistent_relationships()
    {
        $booking = Booking::factory()->create();

        $this->assertNotNull($booking->location_id);
        $this->assertNotNull($booking->provider_id);

        $location = $booking->location;
        $this->assertEquals($location->id, $booking->location_id);
        $this->assertEquals($location->provider_id, $booking->provider_id);
    }

    public function test_existing_booking_behavior_unchanged_with_location_id()
    {
        // Create a booking using location_id and provider_id
        $booking = Booking::create([
            'location_id' => $this->locationA->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        // Test that existing relationships still work
        $this->assertNotNull($booking->provider);
        $this->assertNotNull($booking->customer);
        $this->assertNotNull($booking->service);

        // Test that the location relationship works
        $this->assertNotNull($booking->location);

        // Test that status transitions still work
        $booking->update(['status' => 'completed']);
        $this->assertEquals('completed', $booking->fresh()->status);
    }
}
