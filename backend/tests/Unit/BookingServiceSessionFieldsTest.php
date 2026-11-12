<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService as BookingServiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceSessionFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_booking_service_has_default_scheduled_status()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $bookingService = app(BookingServiceService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
        ]);

        $bookingServiceRecord = BookingService::where('booking_id', $booking->id)->first();

        $this->assertEquals('scheduled', $bookingServiceRecord->session_status);
    }

    public function test_booking_service_session_fields_are_nullable()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $bookingService = app(BookingServiceService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
        ]);

        $bookingServiceRecord = BookingService::where('booking_id', $booking->id)->first();

        $this->assertNull($bookingServiceRecord->started_at);
        $this->assertNull($bookingServiceRecord->actual_end_at);
    }

    public function test_booking_service_can_set_and_retrieve_session_fields()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $bookingService = app(BookingServiceService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
        ]);

        $bookingServiceRecord = BookingService::where('booking_id', $booking->id)->first();

        $startedAt = Carbon::parse('2026-12-01 10:07:00');
        $actualEndAt = Carbon::parse('2026-12-01 11:14:00');

        $bookingServiceRecord->update([
            'started_at' => $startedAt,
            'actual_end_at' => $actualEndAt,
            'session_status' => 'completed',
        ]);

        $bookingServiceRecord->refresh();

        $this->assertEquals($startedAt, $bookingServiceRecord->started_at);
        $this->assertEquals($actualEndAt, $bookingServiceRecord->actual_end_at);
        $this->assertEquals('completed', $bookingServiceRecord->session_status);
    }

    public function test_booking_service_datetime_casts_work_correctly()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $bookingService = app(BookingServiceService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
        ]);

        $bookingServiceRecord = BookingService::where('booking_id', $booking->id)->first();

        $bookingServiceRecord->update([
            'started_at' => '2026-12-01 10:07:00',
            'actual_end_at' => '2026-12-01 11:14:00',
        ]);

        $bookingServiceRecord->refresh();

        $this->assertInstanceOf(Carbon::class, $bookingServiceRecord->started_at);
        $this->assertInstanceOf(Carbon::class, $bookingServiceRecord->actual_end_at);
    }

    public function test_existing_booking_service_behavior_unchanged()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $bookingService = app(BookingServiceService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
        ]);

        $bookingServiceRecord = BookingService::where('booking_id', $booking->id)->first();

        // Existing fields still work
        $this->assertNotNull($bookingServiceRecord->start_time);
        $this->assertNotNull($bookingServiceRecord->end_time);
        $this->assertEquals($service->id, $bookingServiceRecord->service_id);
        $this->assertEquals($booking->id, $bookingServiceRecord->booking_id);

        // Relationships still work
        $this->assertNotNull($bookingServiceRecord->booking);
        $this->assertNotNull($bookingServiceRecord->service);
    }

    public function test_multiple_booking_services_can_have_independent_session_states()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create();
        $service1 = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30]);
        $service2 = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        // Create two separate booking services manually for this test
        $booking = Booking::create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'service_id' => $service1->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $bookingService1 = BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service1->id,
            'start_time' => Carbon::parse('2026-12-01 10:00:00'),
            'end_time' => Carbon::parse('2026-12-01 10:30:00'),
        ]);

        $bookingService2 = BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service2->id,
            'start_time' => Carbon::parse('2026-12-01 10:35:00'),
            'end_time' => Carbon::parse('2026-12-01 11:35:00'),
        ]);

        // Update first service to in_progress
        $bookingService1->update([
            'started_at' => Carbon::parse('2026-12-01 10:00:00'),
            'session_status' => 'in_progress',
        ]);

        $bookingService1->refresh();
        $bookingService2->refresh();

        $this->assertEquals('in_progress', $bookingService1->session_status);
        $this->assertEquals('scheduled', $bookingService2->session_status);
        $this->assertNull($bookingService2->started_at);
    }
}
