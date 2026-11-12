<?php

namespace Tests\Feature;

use App\Models\Booking;
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

class BookingTimezoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_time_stored_in_provider_timezone()
    {
        // Setup: Create provider with Africa/Kampala timezone (UTC+3)
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create([
            'provider_id' => $provider->id,
            'timezone' => 'Africa/Kampala',
        ]);
        
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

        // Set up schedules
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

        // Book at 14:00 in Africa/Kampala timezone
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

        // Verify time is stored as HH:MM string (no timezone)
        $this->assertEquals('14:00', $booking->time);
    }

    public function test_booking_time_consistent_across_retrieval()
    {
        // Setup: Create provider with specific timezone
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'America/New_York',
        ]);
        $location = Location::factory()->create([
            'provider_id' => $provider->id,
            'timezone' => 'America/New_York',
        ]);
        
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

        // Set up schedules
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

        // Book at 10:00 in America/New_York timezone
        $booking = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);

        // Retrieve booking from database
        $retrievedBooking = Booking::find($booking->id);

        // Verify time is stored as HH:MM string (no timezone)
        $this->assertEquals('10:00', $retrievedBooking->time);
    }
}
