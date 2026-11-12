<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\AvailabilityEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAvailabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityEngine $availabilityEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityEngine = app(AvailabilityEngine::class);
    }

    private function createBookingWithSession(array $sessionConfig = []): array
    {
        $testDate = Carbon::now()->addDays(5)->format('Y-m-d');
        Carbon::setTestNow($testDate . ' 09:00:00');

        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $specialist = Specialist::factory()->create();
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'status' => 'ACTIVE',
        ]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $booking = Booking::create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'service_id' => $service->id,
            'date' => $testDate,
            'time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $bookingService = BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'start_time' => Carbon::parse($testDate . ' 10:00:00'),
            'end_time' => Carbon::parse($testDate . ' 11:00:00'),
            'session_status' => $sessionConfig['session_status'] ?? 'scheduled',
            'started_at' => $sessionConfig['started_at'] ?? null,
            'actual_end_at' => $sessionConfig['actual_end_at'] ?? null,
        ]);

        Carbon::setTestNow();

        return [
            'booking' => $booking,
            'bookingService' => $bookingService,
            'provider' => $provider,
            'location' => $location,
            'specialist' => $specialist,
            'service' => $service,
            'testDate' => $testDate,
        ];
    }

    public function test_existing_booking_availability_remains_unchanged()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate] = $this->createBookingWithSession([
            'session_status' => 'scheduled',
        ]);

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // The 10:00 slot should be blocked by the existing booking
        $slots = $availability->slots->toArray();
        $hasSlotAt10 = collect($slots)->contains('start', '10:00');
        
        $this->assertFalse($hasSlotAt10, '10:00 slot should be blocked by existing booking');
    }

    public function test_in_progress_session_before_expected_end()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Manually update the session to have a late start
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:07:00'),
        ]);

        Carbon::setTestNow($testDate . ' 10:30:00');

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Expected end is 11:07 (10:07 + 60min)
        // Booking end is 11:00
        // Should be blocked until 11:07 (max of both)
        $slots = $availability->slots->toArray();
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked by in-progress session');
    }

    public function test_in_progress_session_extending_beyond_booking_end()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Manually update the session to have started on time
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
        ]);

        Carbon::setTestNow($testDate . ' 10:30:00');

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Booking end is 11:00
        // Session expected end is 11:00 (10:00 + 60min)
        // Should be blocked until 11:00
        $slots = $availability->slots->toArray();
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked');
    }

    public function test_in_progress_overtime_extending_beyond_booking_end()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Manually update the session to have started on time
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
        ]);

        Carbon::setTestNow($testDate . ' 11:15:00');

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Booking end is 11:00
        // Session is still in-progress at 11:15 (overtime)
        // Should be blocked until at least 11:15
        $slots = $availability->slots->toArray();
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        $hasSlotAt1115 = collect($slots)->contains('start', '11:15');
        
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked');
        $this->assertFalse($hasSlotAt1115, '11:15 slot should be blocked by overtime');
    }

    public function test_completed_session_does_not_shorten_booking_reservation()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'completed',
        ]);

        // Manually update the session to have completed early
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
            'actual_end_at' => Carbon::parse($testDate . ' 10:45:00'),
        ]);

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Session completed at 10:45
        // Booking end is 11:00
        // Should still be blocked until 11:00 (booking reservation preserved)
        $slots = $availability->slots->toArray();
        $hasSlotAt1045 = collect($slots)->contains('start', '10:45');
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        
        $this->assertFalse($hasSlotAt1045, '10:45 slot should be blocked by booking reservation');
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked by booking reservation');
    }

    public function test_early_completion_preserves_booking_end()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'completed',
        ]);

        // Manually update the session to have completed early
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
            'actual_end_at' => Carbon::parse($testDate . ' 10:45:00'),
        ]);

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Early completion at 10:45 should NOT release the booking window
        // Booking should still block until 11:00
        $slots = $availability->slots->toArray();
        
        // Verify that slots before 11:00 are blocked
        $slotsBefore11 = collect($slots)->filter(fn($slot) => $slot['start'] < '11:00');
        $this->assertEmpty($slotsBefore11->toArray(), 'All slots before 11:00 should be blocked');
    }

    public function test_live_session_can_extend_but_never_shorten_reservation()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Manually update the session to have a late start
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:07:00'),
        ]);

        Carbon::setTestNow($testDate . ' 10:30:00');

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Booking: 10:00-11:00
        // Session: 10:07 started, expected end 11:07
        // Should block until max(11:00, 11:07) = 11:07
        $slots = $availability->slots->toArray();
        
        // Verify slots before 11:00 are blocked (by booking)
        $slotsBefore11 = collect($slots)->filter(fn($slot) => $slot['start'] < '11:00');
        $this->assertEmpty($slotsBefore11->toArray(), 'Slots before 11:00 should be blocked');
    }

    public function test_effective_duration_uses_base_service_duration()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'service' => $service, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Manually update the session to have started on time
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
        ]);

        Carbon::setTestNow($testDate . ' 10:30:00');

        // Service duration is 60 minutes
        // Expected end should be 11:00
        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Should be blocked until at least 11:00
        $slots = $availability->slots->toArray();
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked');
    }

    public function test_multi_service_booking_parent_booking_blocks_overall_window()
    {
        $testDate = Carbon::now()->addDays(5)->format('Y-m-d');
        Carbon::setTestNow($testDate . ' 09:00:00');

        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $specialist = Specialist::factory()->create();
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'status' => 'ACTIVE',
        ]);
        $customer = Customer::factory()->create();
        $serviceA = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30]);
        $serviceB = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $booking = Booking::create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'service_id' => $serviceA->id,
            'date' => $testDate,
            'time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $serviceA->id,
            'start_time' => Carbon::parse($testDate . ' 10:00:00'),
            'end_time' => Carbon::parse($testDate . ' 10:30:00'),
            'session_status' => 'scheduled',
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $serviceB->id,
            'start_time' => Carbon::parse($testDate . ' 10:35:00'),
            'end_time' => Carbon::parse($testDate . ' 11:35:00'),
            'session_status' => 'scheduled',
        ]);

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $serviceA->id,
            $specialist->id
        );

        // Parent booking should block the overall window
        // Individual booking_service timing is internal
        $slots = $availability->slots->toArray();
        $hasSlotAt10 = collect($slots)->contains('start', '10:00');
        $hasSlotAt1035 = collect($slots)->contains('start', '10:35');
        
        $this->assertFalse($hasSlotAt10, '10:00 slot should be blocked');
        $this->assertFalse($hasSlotAt1035, '10:35 slot should be blocked by parent booking');
    }

    public function test_scheduled_session_adds_nothing_beyond_existing_booking()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'testDate' => $testDate] = $this->createBookingWithSession([
            'session_status' => 'scheduled',
        ]);

        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Scheduled session should not add any constraint beyond existing booking
        // Booking already blocks 10:00-11:00
        $slots = $availability->slots->toArray();
        $hasSlotAt10 = collect($slots)->contains('start', '10:00');
        
        $this->assertFalse($hasSlotAt10, '10:00 slot should be blocked by existing booking');

        Carbon::setTestNow();
    }

    public function test_effective_duration_uses_location_override_when_present()
    {
        ['booking' => $booking, 'provider' => $provider, 'location' => $location, 'service' => $service, 'testDate' => $testDate, 'bookingService' => $bookingService] = $this->createBookingWithSession([
            'session_status' => 'in_progress',
        ]);

        // Create a location-specific duration override
        \DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'duration_override' => 90, // Override to 90 minutes (base is 60)
            'is_available' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Manually update the session to have started on time
        $bookingService->update([
            'started_at' => Carbon::parse($testDate . ' 10:00:00'),
        ]);

        Carbon::setTestNow($testDate . ' 10:30:00');

        // Service base duration is 60 minutes, but location override is 90 minutes
        // Expected end should be 11:30 (10:00 + 90min) due to override
        $availability = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Should be blocked until at least 11:30 (override duration)
        $slots = $availability->slots->toArray();
        $hasSlotAt11 = collect($slots)->contains('start', '11:00');
        $hasSlotAt1130 = collect($slots)->contains('start', '11:30');
        
        $this->assertFalse($hasSlotAt11, '11:00 slot should be blocked by override duration');
        $this->assertFalse($hasSlotAt1130, '11:30 slot should be blocked by override duration');

        Carbon::setTestNow();
    }
}

