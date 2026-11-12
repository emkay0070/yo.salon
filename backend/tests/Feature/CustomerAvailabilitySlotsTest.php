<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Provider;
use App\Models\Location;
use App\Models\LocationSchedule;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Plan;
use App\Services\CustomerAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Test that CustomerAvailabilityService returns all slots with availability flags
 * for the public booking flow, not just available slots.
 */
class CustomerAvailabilitySlotsTest extends TestCase
{
    use RefreshDatabase;

    private CustomerAvailabilityService $availabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = app(CustomerAvailabilityService::class);
    }

    /**
     * Verify that the availability API returns all time slots (both available and unavailable)
     * with the correct availability flag for the public booking flow.
     */
    public function test_availability_returns_all_slots_with_availability_flags(): void
    {
        $provider = Provider::create([
            'id'           => Str::uuid(),
            'display_name' => 'Test Provider',
            'type'         => 'independent_specialist',
            'timezone'     => 'Africa/Kampala',
        ]);

        $specialist = Specialist::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Test Specialist',
            'email'       => 'specialist@example.com',
            'phone'       => '0700000001',
        ]);

        $service = Service::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Haircut',
            'duration'    => 60,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'price'       => 5000,
            'currency'    => 'UGX',
        ]);

        $date = Carbon::tomorrow();
        $dayInt = $date->dayOfWeek;

        \DB::table('provider_schedules')->insert([
            'id' => Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $dayInt,
            'is_closed' => false,
            'open_time' => '09:00:00',
            'close_time' => '12:00:00',
        ]);

        SpecialistAssignment::create([
            'id'            => Str::uuid(),
            'provider_id'   => $provider->id,
            'location_id'   => null,
            'specialist_id' => $specialist->id,
            'status'        => 'ACTIVE',
            'is_primary'    => true,
        ]);

        // Create a booking at 10:00
        $booking = \App\Models\Booking::create([
            'id'           => Str::uuid(),
            'provider_id'  => $provider->id,
            'location_id'  => null,
            'specialist_id' => $specialist->id,
            'service_id'   => $service->id,
            'date'         => $date->format('Y-m-d'),
            'time'         => '10:00',
            'status'       => 'confirmed',
        ]);

        \App\Models\BookingService::create([
            'id'         => Str::uuid(),
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'start_time' => Carbon::parse("{$date->format('Y-m-d')} 10:00:00"),
            'end_time'   => Carbon::parse("{$date->format('Y-m-d')} 11:00:00"),
        ]);

        // Request availability
        $result = $this->availabilityService->getAvailableSlots(
            $provider->id,
            null,
            $service->id,
            $specialist->id,
            $date->format('Y-m-d')
        );

        // Debug: check result structure
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('is_closed', $result, 'Result should have is_closed key');
        $this->assertFalse($result['is_closed'], 'Should not be closed');

        // Verify that slots are returned
        $this->assertNotEmpty($result['slots'], 'Slots should be returned. Result: ' . json_encode($result));

        // Verify that 10:00 is marked as unavailable
        $has1000 = false;
        $has1000Available = false;
        foreach ($result['slots'] as $slot) {
            if ($slot['time'] === '10:00') {
                $has1000 = true;
                $has1000Available = $slot['available'];
                break;
            }
        }

        $this->assertTrue($has1000, '10:00 slot should be present in results');
        $this->assertFalse($has1000Available, '10:00 should be marked as unavailable due to existing booking');

        // Verify that other slots (e.g., 09:00) are marked as available
        $has0900 = false;
        $has0900Available = false;
        foreach ($result['slots'] as $slot) {
            if ($slot['time'] === '09:00') {
                $has0900 = true;
                $has0900Available = $slot['available'];
                break;
            }
        }

        $this->assertTrue($has0900, '09:00 slot should be present in results');
        $this->assertTrue($has0900Available, '09:00 should be marked as available');
    }
}
