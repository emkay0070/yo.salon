<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Provider;
use App\Models\Location;
use App\Models\LocationSchedule;
use App\Models\ProviderSchedule;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Salon;
use App\Services\AvailabilityEngine;
use App\Domain\Availability\DTOs\ProviderLocationContext;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 5B — Provider → Location API
 *
 * Tests the new primary getAvailableSlots(providerId, locationId, date) API.
 * No Salon involvement in the test assertions — Salon is not a domain concept here.
 */
class AvailabilityEnginePhase5Test extends TestCase
{
    use RefreshDatabase;

    private AvailabilityEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(AvailabilityEngine::class);
    }

    /**
     * Independent specialist (locationId = null):
     * schedule must resolve from ProviderSchedule.
     */
    public function test_independent_specialist_uses_provider_schedule(): void
    {
        $provider = Provider::create([
            'id'           => Str::uuid(),
            'display_name' => 'Indie Provider',
            'type'         => 'independent_specialist',
        ]);

        $plan = Plan::create([
            'id' => Str::uuid(),
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 0,
            'currency' => 'UGX',
            'features' => [],
            'is_active' => true,
        ]);

        // Subscription required by preflight
        Subscription::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'status'      => 'active',
            'plan_id'     => $plan->id,
            'starts_at'   => Carbon::now(),
        ]);

        $specialist = Specialist::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Indie Specialist',
            'email'       => 'indie@example.com',
            'phone'       => '0700000001',
        ]);

        $service = Service::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Haircut',
            'duration'    => 60,
            'price'       => 5000,
            'currency'    => 'UGX',
        ]);

        $date    = Carbon::tomorrow();
        $dayInt  = $date->dayOfWeek; // integer 0-6

        // Create ProviderSchedule (open)
        ProviderSchedule::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $dayInt,
            'is_closed'   => false,
            'open_time'   => '09:00:00',
            'close_time'  => '17:00:00',
        ]);

        // Act — independent: locationId = null
        $result = $this->engine->getAvailableSlots(
            $provider->id,
            null,
            $date->format('Y-m-d'),
            $service->id,
        );

        // Assert — independent uses ProviderSchedule, should have slots
        $this->assertFalse($result->isClosed,
            'Independent specialist should NOT be marked closed when ProviderSchedule is open.');
        $this->assertGreaterThan(0, $result->totalAvailableSlots,
            'Independent specialist should have available slots from ProviderSchedule.');
    }

    /**
     * Branch specialist (locationId set):
     * schedule must resolve from LocationSchedule, overriding a closed ProviderSchedule.
     */
    public function test_branch_uses_location_schedule(): void
    {
        $provider = Provider::create([
            'id'           => Str::uuid(),
            'display_name' => 'Branch Provider',
            'type'         => 'salon',
        ]);

        $plan = Plan::create([
            'id' => Str::uuid(),
            'name' => 'Test Plan 2',
            'slug' => 'test-plan-2',
            'price' => 0,
            'currency' => 'UGX',
            'features' => [],
            'is_active' => true,
        ]);

        Subscription::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'status'      => 'active',
            'plan_id'     => $plan->id,
            'starts_at'   => Carbon::now(),
        ]);

        $location = Location::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Branch A',
            'slug'        => 'branch-a-' . Str::random(4),
            'address'     => '123 Branch St',
        ]);

        $service = Service::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Haircut',
            'duration'    => 60,
            'price'       => 5000,
            'currency'    => 'UGX',
        ]);

        $date    = Carbon::tomorrow();
        $dayInt  = $date->dayOfWeek;
        $dayName = $date->format('l'); // "Monday", "Tuesday" etc.

        // ProviderSchedule is CLOSED — branch should override this
        ProviderSchedule::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $dayInt,
            'is_closed'   => true,
            'open_time'   => '00:00:00',
            'close_time'  => '00:00:00',
        ]);

        // LocationSchedule is OPEN
        LocationSchedule::create([
            'id'          => Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $dayName,
            'is_closed'   => false,
            'open_time'   => '09:00:00',
            'close_time'  => '17:00:00',
        ]);

        // Act — branch: locationId set
        $result = $this->engine->getAvailableSlots(
            $provider->id,
            $location->id,
            $date->format('Y-m-d'),
            $service->id,
        );

        // Assert — branch uses LocationSchedule (open), ignoring Provider's closed schedule
        $this->assertFalse($result->isClosed,
            'Branch should NOT be closed when LocationSchedule is open, even if ProviderSchedule is closed.');
        $this->assertGreaterThan(0, $result->totalAvailableSlots,
            'Branch should have available slots from LocationSchedule.');
    }

    /**
     * ProviderLocationContext::fromProvider() works correctly.
     */
    public function test_context_from_provider_bridges_correctly(): void
    {
        $provider = Provider::create([
            'id'           => Str::uuid(),
            'display_name' => 'Bridge Provider',
            'type'         => 'salon',
        ]);

        $location = Location::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Bridge Location',
            'slug'        => 'bridge-location-' . Str::random(4),
            'address'     => '1 Bridge Rd',
        ]);

        $ctx = ProviderLocationContext::fromProvider($provider, $location);

        $this->assertEquals($provider->id, $ctx->providerId);
        $this->assertEquals($location->id, $ctx->locationId);
        $this->assertFalse($ctx->isIndependent());
    }

    /**
     * Independent context is correctly flagged.
     */
    public function test_independent_context_flag(): void
    {
        $ctx = new ProviderLocationContext('provider-123', null);

        $this->assertTrue($ctx->isIndependent());
        $this->assertStringContainsString('null', $ctx->cacheKey());
    }

    /**
     * Cross-service availability: booking Service A at 10:30 should block
     * Service B availability for the same specialist at overlapping times.
     */
    public function test_cross_service_availability_blocks_conflicting_slots(): void
    {
        $provider = Provider::create([
            'id'           => Str::uuid(),
            'display_name' => 'Test Provider',
            'type'         => 'independent_specialist',
        ]);

        $plan = Plan::create([
            'id' => Str::uuid(),
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 0,
            'currency' => 'UGX',
            'features' => [],
            'is_active' => true,
        ]);

        Subscription::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'status'      => 'active',
            'plan_id'     => $plan->id,
            'starts_at'   => Carbon::now(),
        ]);

        $specialist = Specialist::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Test Specialist',
            'email'       => 'specialist@example.com',
            'phone'       => '0700000001',
        ]);

        $serviceA = Service::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Haircut',
            'duration'    => 60,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'price'       => 5000,
            'currency'    => 'UGX',
        ]);

        $serviceB = Service::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'name'        => 'Coloring',
            'duration'    => 90,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'price'       => 15000,
            'currency'    => 'UGX',
        ]);

        $date = Carbon::tomorrow();
        $dayInt = $date->dayOfWeek;

        ProviderSchedule::create([
            'id'          => Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $dayInt,
            'is_closed'   => false,
            'open_time'   => '09:00:00',
            'close_time'  => '17:00:00',
        ]);

        SpecialistAssignment::create([
            'id'            => Str::uuid(),
            'provider_id'   => $provider->id,
            'location_id'   => null,
            'specialist_id' => $specialist->id,
            'status'        => 'ACTIVE',
            'is_primary'    => true,
        ]);

        // Create a booking for Service A at 10:30
        $booking = \App\Models\Booking::create([
            'id'           => Str::uuid(),
            'provider_id'  => $provider->id,
            'location_id'  => null,
            'specialist_id' => $specialist->id,
            'service_id'   => $serviceA->id,
            'date'         => $date->format('Y-m-d'),
            'time'         => '10:30',
            'status'       => 'confirmed',
        ]);

        \App\Models\BookingService::create([
            'id'         => Str::uuid(),
            'booking_id' => $booking->id,
            'service_id' => $serviceA->id,
            'start_time' => Carbon::parse("{$date->format('Y-m-d')} 10:30:00"),
            'end_time'   => Carbon::parse("{$date->format('Y-m-d')} 11:30:00"),
        ]);

        // Request availability for Service B (different service, same specialist)
        $result = $this->engine->getAvailableSlots(
            $provider->id,
            null,
            $date->format('Y-m-d'),
            $serviceB->id,
            $specialist->id
        );

        // Get available slots
        $slots = $result->slots->toArray();
        $this->assertNotEmpty($slots, 'Should have some slots');

        // 10:30 should be present but marked as unavailable (blocked by existing booking)
        $slot1030 = collect($slots)->firstWhere('start', '10:30');
        $this->assertNotNull($slot1030, '10:30 slot should be present in results');
        $this->assertFalse($slot1030['available'] ?? true, '10:30 should be marked as unavailable due to existing booking for Service A');
    }
}
