<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService as BookingServiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServicePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_guest_booking_creates_single_booking_service_with_uuid_and_timing()
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
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '10:00:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ]);

        // Verify exactly one booking_service row exists
        $bookingServices = BookingService::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $bookingServices);

        $bookingServiceRecord = $bookingServices->first();

        // Verify UUID is present and valid
        $this->assertNotNull($bookingServiceRecord->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $bookingServiceRecord->id);

        // Verify timing fields are populated
        $this->assertNotNull($bookingServiceRecord->start_time);
        $this->assertNotNull($bookingServiceRecord->end_time);

        // Verify Booking::services() still returns expected services
        $services = $booking->services()->get();
        $this->assertCount(1, $services);
        $this->assertEquals($service->id, $services->first()->id);

        // Verify no duplicate booking_service rows exist
        $this->assertEquals(1, BookingService::where('booking_id', $booking->id)->where('service_id', $service->id)->count());
    }

    public function test_create_booking_with_account_creates_single_booking_service_with_uuid_and_timing()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $service = Service::factory()->create(['duration' => 60, 'provider_id' => $provider->id]);

        $bookingService = app(BookingServiceService::class);

        $result = $bookingService->createBookingWithAccount([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
            'service_id' => $service->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '10:00:00',
            'internal_booking' => true, // Bypass slot availability validation for test
        ]);

        $booking = $result['booking'];

        // Verify exactly one booking_service row exists
        $bookingServices = BookingService::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $bookingServices);

        $bookingServiceRecord = $bookingServices->first();

        // Verify UUID is present and valid
        $this->assertNotNull($bookingServiceRecord->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $bookingServiceRecord->id);

        // Verify timing fields are populated
        $this->assertNotNull($bookingServiceRecord->start_time);
        $this->assertNotNull($bookingServiceRecord->end_time);

        // Verify Booking::services() still returns expected services
        $services = $booking->services()->get();
        $this->assertCount(1, $services);
        $this->assertEquals($service->id, $services->first()->id);

        // Verify no duplicate booking_service rows exist
        $this->assertEquals(1, BookingService::where('booking_id', $booking->id)->where('service_id', $service->id)->count());
    }

    public function test_transition_status_creates_booking_activity_record()
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        // Clear any existing activities from booking creation
        BookingActivity::where('booking_id', $booking->id)->delete();

        $bookingService = app(BookingServiceService::class);

        // Transition status
        $updated = $bookingService->transitionStatus(
            $booking,
            'arrived',
            'staff',
            'staff-123',
            'Customer arrived'
        );

        // Verify status changed
        $this->assertEquals('arrived', $updated->status);
        $this->assertNotNull($updated->arrived_at);

        // Verify activity record was created
        $activities = BookingActivity::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $activities);

        $activity = $activities->first();
        $this->assertEquals('status_changed', $activity->type);
        $this->assertEquals('staff', $activity->actor_type);
        $this->assertEquals('staff-123', $activity->actor_id);
        $this->assertStringContainsString('arrived', $activity->title);
        $this->assertArrayHasKey('previous_status', $activity->data);
        $this->assertArrayHasKey('new_status', $activity->data);
        $this->assertEquals('confirmed', $activity->data['previous_status']);
        $this->assertEquals('arrived', $activity->data['new_status']);
    }

    public function test_transition_status_records_operational_timestamps()
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $bookingService = app(BookingServiceService::class);

        // Transition to arrived
        $arrived = $bookingService->transitionStatus($booking, 'arrived');
        $this->assertNotNull($arrived->arrived_at);
        $this->assertNull($arrived->waiting_since);

        // Transition to waiting
        $waiting = $bookingService->transitionStatus($arrived, 'waiting');
        $this->assertNotNull($waiting->arrived_at);
        $this->assertNotNull($waiting->waiting_since);
    }

    public function test_transition_status_validates_state_transitions()
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $bookingService = app(BookingServiceService::class);

        // Invalid transition: confirmed -> completed (must go through in_progress)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $bookingService->transitionStatus($booking, 'completed');
    }

    public function test_booking_has_operational_state_columns()
    {
        $booking = Booking::factory()->create();
        $booking->refresh();

        // Verify new columns exist and have default values
        $this->assertEquals('upcoming', $booking->operational_state);
        $this->assertNull($booking->grace_period_started_at);
        $this->assertNull($booking->grace_period_ends_at);
    }
}
