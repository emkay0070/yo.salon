<?php

namespace Tests\Feature;

use App\Models\AssignmentSchedule;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rejected_when_time_outside_assignment_schedule()
    {
        // Setup: Create location-based provider
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules for availability
        $testDate = CarbonImmutable::now()->addDays(1);
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $customer = Customer::factory()->create();

        $bookingService = app(BookingService::class);

        // Attempt to book at 22:00 (outside 09:00-17:00 schedule)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not available/');

        $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '22:00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);
    }

    public function test_booking_accepted_when_time_within_assignment_schedule()
    {
        // Setup: Create location-based provider
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules for availability
        $testDate = CarbonImmutable::now()->addDays(1);
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $customer = Customer::factory()->create();

        $bookingService = app(BookingService::class);

        // Book at 14:00 (within 09:00-17:00 schedule)
        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '14:00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);

        // Verify booking created successfully
        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($provider->id, $booking->provider_id);
        $this->assertEquals($specialist->id, $booking->specialist_id);
        $this->assertEquals('14:00', $booking->time);
    }

    public function test_booking_rejected_when_time_not_in_available_slots()
    {
        // Setup: Create location-based provider
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules for availability
        $testDate = CarbonImmutable::now()->addDays(1);
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $bookingService = app(BookingService::class);

        // Attempt to book at 09:13 (not a standard 15-minute slot)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not available/');

        $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '09:13',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);
    }
}
