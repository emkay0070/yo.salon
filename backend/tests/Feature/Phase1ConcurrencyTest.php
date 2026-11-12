<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\BookingConflictService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Specialist $specialist;
    private Service $service;
    private SpecialistAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test data
        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->specialist = Specialist::factory()->create();
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
    }

    /**
     * Test identical intervals - at most one should succeed
     */
    public function test_identical_intervals_conflict()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();
        $testTime = '10:00';

        // Create first booking
        $booking1 = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => $testTime,
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $booking1->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} {$testTime}"),
            'end_time' => Carbon::parse("{$testDate} {$testTime}")->addMinutes(60),
        ]);

        // Try to create second booking with identical interval
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate, $testTime) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} {$testTime}");
            $serviceEnd = $serviceStart->copy()->addMinutes($this->service->duration);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'Identical intervals should conflict');
        });
    }

    /**
     * Test partially overlapping intervals
     */
    public function test_partially_overlapping_intervals_conflict()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create booking A: 10:00-11:00
        $bookingA = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $bookingA->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} 10:00"),
            'end_time' => Carbon::parse("{$testDate} 11:00"),
        ]);

        // Check if booking B: 10:30-11:30 conflicts
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:30");
            $serviceEnd = $serviceStart->copy()->addMinutes(60);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'Partially overlapping intervals should conflict');
        });
    }

    /**
     * Test buffer-only conflicts
     */
    public function test_buffer_only_conflicts()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create booking A: 10:00-10:30 with buffer_after: 10 minutes → occupied until 10:40
        $serviceA = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 30,
            'buffer_after' => 10,
        ]);

        $bookingA = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $serviceA->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $bookingA->id,
            'service_id' => $serviceA->id,
            'start_time' => Carbon::parse("{$testDate} 10:00"),
            'end_time' => Carbon::parse("{$testDate} 10:30"),
        ]);

        // Check if booking B: 10:35-11:05 with buffer_before: 5 minutes → occupied from 10:30 conflicts
        $serviceB = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'duration' => 30,
            'buffer_before' => 5,
        ]);

        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate, $serviceB) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:35");
            $serviceEnd = $serviceStart->copy()->addMinutes(30);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $serviceB
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'Buffer-only overlap should conflict');
        });
    }

    /**
     * Test independent specialists - both should succeed
     */
    public function test_independent_specialists_no_conflict()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create specialist 2
        $specialist2 = Specialist::factory()->create();
        $assignment2 = SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist2->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'ACTIVE',
        ]);

        // Create booking for specialist 1: 10:00-11:00
        $booking1 = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $booking1->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} 10:00"),
            'end_time' => Carbon::parse("{$testDate} 11:00"),
        ]);

        // Check if booking for specialist 2 at same time conflicts
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate, $specialist2) {
            $conflictService->acquireSpecialistLock($specialist2->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:00");
            $serviceEnd = $serviceStart->copy()->addMinutes($this->service->duration);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $specialist2->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertFalse($hasConflict, 'Independent specialists should not conflict');
        });
    }

    /**
     * Test expired reserved booking - should not block
     */
    public function test_expired_reserved_booking_does_not_block()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create expired reserved booking
        $booking = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'reserved',
            'slot_locked_until' => Carbon::now()->subMinutes(10), // Expired
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} 10:00"),
            'end_time' => Carbon::parse("{$testDate} 11:00"),
        ]);

        // Check if new booking conflicts
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:00");
            $serviceEnd = $serviceStart->copy()->addMinutes($this->service->duration);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertFalse($hasConflict, 'Expired reserved booking should not block');
        });
    }

    /**
     * Test cross-midnight booking
     */
    public function test_cross_midnight_booking()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create booking crossing midnight: 23:45-00:30
        $booking = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '23:45',
            'status' => 'confirmed',
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} 23:45"),
            'end_time' => Carbon::parse("{$testDate} 23:45")->addMinutes(45),
        ]);

        // Check if booking at 00:15 (same booking date, next day time) conflicts
        // The booking at 23:45 ends at 00:30 the next day, so 00:15 should overlap
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            // Parse 00:15 as the next day after the booking date
            $serviceStart = Carbon::parse("{$testDate} 23:45")->addDay()->setTime(0, 15);
            $serviceEnd = $serviceStart->copy()->addMinutes(30);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'Cross-midnight overlap should conflict');
        });
    }

    /**
     * Test legacy booking without BookingService record
     */
    public function test_legacy_booking_conflict_detection()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create legacy booking WITHOUT BookingService record
        $legacyBooking = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        // Check if new booking at same time conflicts
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:00");
            $serviceEnd = $serviceStart->copy()->addMinutes($this->service->duration);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'Legacy booking should be detected in conflict check');
        });
    }

    /**
     * Test lifecycle-aware status check - pending_payment reserves time
     */
    public function test_pending_payment_reserves_time()
    {
        $testDate = CarbonImmutable::now()->addDays(1)->toDateString();

        // Create booking with pending_payment status
        $booking = Booking::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $this->service->id,
            'customer_id' => Customer::factory()->create()->id,
            'date' => $testDate,
            'time' => '10:00',
            'status' => 'pending_payment',
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $this->service->id,
            'start_time' => Carbon::parse("{$testDate} 10:00"),
            'end_time' => Carbon::parse("{$testDate} 11:00"),
        ]);

        // Check if new booking conflicts
        $conflictService = app(BookingConflictService::class);

        DB::transaction(function () use ($conflictService, $testDate) {
            $conflictService->acquireSpecialistLock($this->specialist->id, $this->provider->id);

            $serviceStart = Carbon::parse("{$testDate} 10:00");
            $serviceEnd = $serviceStart->copy()->addMinutes($this->service->duration);
            $occupiedInterval = $conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $this->service
            );

            $hasConflict = $conflictService->hasIntervalConflict(
                $this->specialist->id,
                $this->location->id,
                $occupiedInterval['start'],
                $occupiedInterval['end']
            );

            $this->assertTrue($hasConflict, 'pending_payment status should reserve time');
        });
    }

    /**
     * Test occupied interval calculation preserves service timestamps
     */
    public function test_occupied_interval_calculation()
    {
        $conflictService = app(BookingConflictService::class);

        $serviceStart = Carbon::parse('2026-10-15 10:00');
        $serviceEnd = Carbon::parse('2026-10-15 11:00');

        $occupiedInterval = $conflictService->calculateOccupiedInterval(
            $serviceStart,
            $serviceEnd,
            $this->service
        );

        // Service timestamps should be unchanged
        $this->assertEquals('10:00', $serviceStart->format('H:i'));
        $this->assertEquals('11:00', $serviceEnd->format('H:i'));

        // Occupied interval should include buffers
        $this->assertEquals('09:50', $occupiedInterval['start']->format('H:i')); // 10:00 - 10 min buffer
        $this->assertEquals('11:10', $occupiedInterval['end']->format('H:i'));   // 11:00 + 10 min buffer
    }
}
