<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\BookingOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Specialist $specialist;
    private Specialist $specialist2;
    private Service $service;
    private SpecialistAssignment $assignment;
    private SpecialistAssignment $assignment2;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test data
        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->specialist = Specialist::factory()->create();
        $this->specialist2 = Specialist::factory()->create();
        $this->service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 60,
            'buffer_before' => 10,
            'buffer_after' => 10,
        ]);
        $this->assignment = SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'ACTIVE',
        ]);
        $this->assignment2 = SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist2->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'ACTIVE',
        ]);

        // Setup provider schedule to make specialist available
        $dayOfWeek = CarbonImmutable::now()->addDays(1)->dayOfWeek;
        ProviderSchedule::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'day_of_week' => $dayOfWeek,
            'open_time' => '08:00',
            'close_time' => '18:00',
        ]);
    }

    /**
     * Test sequential booking requests for same specialist and time slot
     * First succeeds, second fails due to conflict
     * NOTE: This is NOT true concurrent execution - Windows does not support pcntl_fork
     * This test verifies conflict detection logic but does not demonstrate race conditions
     */
    public function test_sequential_same_specialist_booking_second_fails()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();
        $testTime = '10:00';
        $customerId = Customer::factory()->create()->id;

        // Create booking data
        $bookingData = [
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'customer_id' => $customerId,
            'date' => $testDate,
            'time' => $testTime,
            'customer_name' => 'Test Customer',
            'customer_phone' => '1234567890',
            'customer_email' => 'test@example.com',
            'internal_booking' => true, // Bypass availability validation - testing conflict detection
        ];

        // Request 1 - should succeed
        $orchestrator1 = new \App\Services\BookingOrchestrator(
            app(\App\Domain\Availability\Engines\AvailabilityEngine::class),
            app(\App\Services\BookingService::class),
            app(\App\Services\ResourceAllocator::class),
            app(\App\Services\BookingConflictService::class)
        );
        $result1 = $orchestrator1->reserveAndBook($bookingData);
        $this->assertNotNull($result1['booking']);
        $booking1Id = $result1['booking']->id;

        // Request 2 - should fail due to conflict
        $orchestrator2 = new \App\Services\BookingOrchestrator(
            app(\App\Domain\Availability\Engines\AvailabilityEngine::class),
            app(\App\Services\BookingService::class),
            app(\App\Services\ResourceAllocator::class),
            app(\App\Services\BookingConflictService::class)
        );
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no longer available');
        $orchestrator2->reserveAndBook($bookingData);

        // Verify only one booking exists in database
        $bookings = Booking::where('specialist_id', $this->specialist->id)
            ->where('date', $testDate)
            ->where('time', $testTime)
            ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
            ->get();

        $this->assertCount(1, $bookings);
        $this->assertEquals($booking1Id, $bookings->first()->id);
    }

    /**
     * Test sequential booking requests for different specialists at same time
     * Both should succeed
     * NOTE: This is NOT true concurrent execution
     */
    public function test_sequential_different_specialists_both_succeed()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();
        $testTime = '10:00';
        $customerId = Customer::factory()->create()->id;

        // Booking for specialist 1
        $bookingData1 = [
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'customer_id' => $customerId,
            'date' => $testDate,
            'time' => $testTime,
            'customer_name' => 'Test Customer',
            'customer_phone' => '1234567890',
            'customer_email' => 'test1@example.com',
            'internal_booking' => true, // Bypass availability validation - testing conflict detection
        ];

        $orchestrator1 = new \App\Services\BookingOrchestrator(
            app(\App\Domain\Availability\Engines\AvailabilityEngine::class),
            app(\App\Services\BookingService::class),
            app(\App\Services\ResourceAllocator::class),
            app(\App\Services\BookingConflictService::class)
        );
        $result1 = $orchestrator1->reserveAndBook($bookingData1);
        $this->assertNotNull($result1['booking']);

        // Booking for specialist 2 - should succeed
        $bookingData2 = [
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist2->id,
            'customer_id' => $customerId,
            'date' => $testDate,
            'time' => $testTime,
            'customer_name' => 'Test Customer',
            'customer_phone' => '1234567890',
            'customer_email' => 'test2@example.com',
            'internal_booking' => true, // Bypass availability validation - testing conflict detection
        ];

        $orchestrator2 = new \App\Services\BookingOrchestrator(
            app(\App\Domain\Availability\Engines\AvailabilityEngine::class),
            app(\App\Services\BookingService::class),
            app(\App\Services\ResourceAllocator::class),
            app(\App\Services\BookingConflictService::class)
        );
        $result2 = $orchestrator2->reserveAndBook($bookingData2);
        $this->assertNotNull($result2['booking']);

        // Verify both bookings exist in database
        $bookings = Booking::where('date', $testDate)
            ->where('time', $testTime)
            ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
            ->get();

        $this->assertCount(2, $bookings);
    }
}
