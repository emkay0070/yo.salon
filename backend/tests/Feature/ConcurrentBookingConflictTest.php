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

class ConcurrentBookingConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_bookings_for_same_slot_prevented()
    {
        // Setup: Create provider with location
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

        // Create a booking directly in the database to simulate an existing booking
        $existingBooking = Booking::create([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'specialist_id' => $specialist->id,
            'service_id' => $service->id,
            'date' => $testDate->toDateString(),
            'time' => '14:00',
            'status' => 'pending',
            'customer_id' => null,
        ]);
        
        // Create corresponding BookingService record (AvailabilityEngine checks this)
        \App\Models\BookingService::create([
            'booking_id' => $existingBooking->id,
            'service_id' => $service->id,
            'start_time' => \Carbon\Carbon::parse("{$testDate->toDateString()} 14:00", 'Africa/Kampala'),
            'end_time' => \Carbon\Carbon::parse("{$testDate->toDateString()} 14:00", 'Africa/Kampala')->addMinutes(60),
        ]);

        $bookingService = app(BookingService::class);

        // Attempt to create second booking for same specialist/time
        // This should fail due to existing booking check
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not available/');

        $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '14:00',
            'customer_name' => 'Customer 2',
            'customer_phone' => '+1987654321',
            'customer_email' => 'customer2@example.com',
        ]);
    }

    public function test_bookings_for_different_specialists_allowed()
    {
        // Setup: Create provider with location
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist1 = Specialist::factory()->create();
        $assignment1 = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist1->id,
        ]);

        $specialist2 = Specialist::factory()->create();
        $assignment2 = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist2->id,
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
            'assignment_id' => $assignment1->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment2->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $bookingService = app(BookingService::class);

        // Create booking for specialist 1
        $booking1 = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist1->id,
            'date' => $testDate->toDateString(),
            'time' => '14:00',
            'customer_name' => 'Customer 1',
            'customer_phone' => '+1234567890',
            'customer_email' => 'customer1@example.com',
        ]);

        $this->assertInstanceOf(Booking::class, $booking1);

        // Create booking for specialist 2 at same time - should succeed
        $booking2 = $bookingService->createGuestBooking([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist2->id,
            'date' => $testDate->toDateString(),
            'time' => '14:00',
            'customer_name' => 'Customer 2',
            'customer_phone' => '+1987654321',
            'customer_email' => 'customer2@example.com',
        ]);

        $this->assertInstanceOf(Booking::class, $booking2);
        $this->assertNotEquals($booking1->specialist_id, $booking2->specialist_id);
    }
}
