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
use App\Services\SessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SessionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private SessionService $sessionService;
    private AvailabilityEngine $availabilityEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionService = app(SessionService::class);
        $this->availabilityEngine = app(AvailabilityEngine::class);
    }

    private function createConfirmedBookingWithSpecialist(): array
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
            'session_status' => 'scheduled',
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

    public function test_cached_availability_is_invalidated_after_session_start()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'provider' => $provider, 'location' => $location, 'specialist' => $specialist, 'testDate' => $testDate] = $this->createConfirmedBookingWithSpecialist();

        // Query availability to populate cache
        $availability1 = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Verify cache is populated
        $cacheKey = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->service_id}:{$booking->specialist_id}";
        $cachedBefore = Cache::get($cacheKey);
        $this->assertNotNull($cachedBefore, 'Cache should be populated after first query');

        // Start the session
        $this->sessionService->startSession($bookingService, $specialist);

        // Verify cache is cleared
        $cachedAfter = Cache::get($cacheKey);
        $this->assertNull($cachedAfter, 'Cache should be cleared after session start. Cache key: ' . $cacheKey);
    }

    public function test_cached_availability_is_invalidated_after_session_complete()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'provider' => $provider, 'location' => $location, 'specialist' => $specialist, 'testDate' => $testDate] = $this->createConfirmedBookingWithSpecialist();

        // Start the session first
        $this->sessionService->startSession($bookingService, $specialist);

        // Query availability to populate cache with in-progress session
        $availability1 = $this->availabilityEngine->getAvailableSlots($provider->id, $location->id,
            $testDate,
            $booking->service_id,
            $booking->specialist_id
        );

        // Verify cache is populated
        $cacheKey = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->service_id}:{$booking->specialist_id}";
        $this->assertNotNull(Cache::get($cacheKey), 'Cache should be populated after first query');

        // Complete the session
        $this->sessionService->completeSession($bookingService, $specialist);

        // Verify cache is cleared
        $this->assertNull(Cache::get($cacheKey), 'Cache should be cleared after session complete');
    }

    public function test_all_four_availability_cache_variants_are_cleared()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'provider' => $provider, 'location' => $location, 'specialist' => $specialist, 'testDate' => $testDate] = $this->createConfirmedBookingWithSpecialist();

        // Populate all four cache variants
        $this->availabilityEngine->getAvailableSlots($provider->id, $location->id, $testDate, $booking->service_id, $booking->specialist_id);
        $this->availabilityEngine->getAvailableSlots($provider->id, $location->id, $testDate, $booking->service_id, null);
        $this->availabilityEngine->getAvailableSlots($provider->id, $location->id, $testDate, null, $booking->specialist_id);
        $this->availabilityEngine->getAvailableSlots($provider->id, $location->id, $testDate, null, null);

        // Verify all caches are populated
        $key1 = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->service_id}:{$booking->specialist_id}";
        $key2 = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->service_id}";
        $key3 = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->specialist_id}";
        $key4 = "availability:{$provider->id}:{$location->id}:{$testDate}";

        $this->assertNotNull(Cache::get($key1));
        $this->assertNotNull(Cache::get($key2));
        $this->assertNotNull(Cache::get($key3));
        $this->assertNotNull(Cache::get($key4));

        // Start the session
        $this->sessionService->startSession($bookingService, $specialist);

        // Verify all caches are cleared
        $this->assertNull(Cache::get($key1), 'Specific cache should be cleared');
        $this->assertNull(Cache::get($key2), 'No-specialist cache should be cleared');
        $this->assertNull(Cache::get($key3), 'No-service cache should be cleared');
        $this->assertNull(Cache::get($key4), 'General cache should be cleared');
    }

    public function test_cache_is_not_invalidated_on_session_start_failure()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'provider' => $provider, 'location' => $location, 'specialist' => $specialist, 'testDate' => $testDate] = $this->createConfirmedBookingWithSpecialist();

        // Query availability to populate cache
        $this->availabilityEngine->getAvailableSlots($provider->id, $location->id, $testDate, $booking->service_id, $booking->specialist_id);

        $cacheKey = "availability:{$provider->id}:{$location->id}:{$testDate}:{$booking->service_id}:{$booking->specialist_id}";
        $cachedData = Cache::get($cacheKey);

        // Try to start session with wrong specialist (should fail)
        $wrongSpecialist = Specialist::factory()->create();
        SpecialistAssignment::factory()->create([
            'specialist_id' => $wrongSpecialist->id,
            'provider_id' => $booking->provider_id,
            'status' => 'ACTIVE',
        ]);

        try {
            $this->sessionService->startSession($bookingService, $wrongSpecialist);
            $this->fail('Should have thrown validation exception');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Expected
        }

        // Cache should still be intact (not invalidated on failure)
        $this->assertEquals($cachedData, Cache::get($cacheKey), 'Cache should not be cleared on failed session start');
    }
}


