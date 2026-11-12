<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService;
use App\Services\CustomerBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingProviderRelationshipIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_creates_customer_provider_relationship()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        $bookingService = app(BookingService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '10:00',
            'internal_booking' => true,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $count = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $provider->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    // Note: createBookingWithAccount requires availability setup which is out of scope
    // for this integration test. The customer-portal path is the critical path verified below.

    public function test_customer_portal_booking_creates_customer_provider_relationship()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        $customerBookingService = app(CustomerBookingService::class);

        $booking = $customerBookingService->createBooking([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '10:00',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $count = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $provider->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_existing_relationship_is_not_duplicated()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id]);

        DB::table('customer_providers')->insert([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'visits' => 5,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingService = app(BookingService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '10:00',
            'internal_booking' => true,
        ]);

        $count = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $provider->id)
            ->count();
        $this->assertEquals(1, $count);

        $relationship = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $provider->id)
            ->first();
        $this->assertEquals(5, $relationship->visits);
    }
}
