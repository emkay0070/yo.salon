<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies that public booking creation (POST /api/v1/bookings/with-account) always
 * lands on an explicit, valid branch so it is visible in the owner's location-scoped
 * appointments dashboard.
 *
 * Covered cases:
 *  1. Guest booking with an explicit location  -> that location is persisted
 *  2. Guest booking without a location         -> provider's primary active location
 *  3. Multi-location provider, no location     -> the primary branch is chosen
 *  4. Foreign-provider location                -> rejected (403)
 *  5. Inactive location                        -> rejected (403)
 *  6. Specialist not available at the location -> rejected (422)
 *  7. Salon without any location               -> rejected, never silently location-less
 *  8. Resolved booking appears in the location-scoped dashboard query
 */
class PublicBookingLocationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $testDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDate = CarbonImmutable::now()->addDays(1);
    }

    private function makeProviderWithLocation(bool $primary = false): array
    {
        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);

        $location = $primary
            ? Location::factory()->primary()->create(['provider_id' => $provider->id])
            : Location::factory()->create(['provider_id' => $provider->id]);

        return [$provider, $location];
    }

    private function makeServiceAvailableAt(Provider $provider, Location $location): Service
    {
        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'duration' => 30,
            'price' => 50000,
        ]);

        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        return $service;
    }

    private function makeSpecialistAt(Provider $provider, Location $location): Specialist
    {
        $specialist = Specialist::factory()->create();

        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
            'status' => 'ACTIVE',
        ]);

        $this->insertSchedules($provider, $location, $assignment);

        return $specialist;
    }

    private function insertSchedules(Provider $provider, Location $location, SpecialistAssignment $assignment): void
    {
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $this->testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);

        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $this->testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);

        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $this->testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    private function payload(Provider $provider, Service $service, Specialist $specialist, array $overrides = []): array
    {
        return array_merge([
            'provider_id' => $provider->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+256700123456',
            'customer_email' => null,
            'service_id' => [$service->id],
            'specialist_id' => $specialist->id,
            'date' => $this->testDate->toDateString(),
            'time' => '10:00',
        ], $overrides);
    }

    /** 1. Explicit location is honoured and persisted. */
    public function test_public_guest_booking_with_explicit_location_persists_that_location(): void
    {
        [$provider, $location] = $this->makeProviderWithLocation(primary: true);
        $service = $this->makeServiceAvailableAt($provider, $location);
        $specialist = $this->makeSpecialistAt($provider, $location);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist, [
            'location_id' => $location->id,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('booking.id'),
            'provider_id' => $provider->id,
            'location_id' => $location->id,
        ]);
    }

    /** 2. Omitted location resolves to the provider's primary location. */
    public function test_public_guest_booking_without_location_resolves_primary_location(): void
    {
        [$provider, $location] = $this->makeProviderWithLocation(primary: true);
        $service = $this->makeServiceAvailableAt($provider, $location);
        $specialist = $this->makeSpecialistAt($provider, $location);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist));

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('booking.id'),
            'location_id' => $location->id,
        ]);
    }

    /** 3. Multi-location provider with no location resolves the primary branch. */
    public function test_multi_location_provider_resolves_primary_branch_when_location_omitted(): void
    {
        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);

        $primaryLocation = Location::factory()->primary()->create(['provider_id' => $provider->id]);
        $secondaryLocation = Location::factory()->create(['provider_id' => $provider->id]);

        $service = $this->makeServiceAvailableAt($provider, $primaryLocation);
        $specialist = $this->makeSpecialistAt($provider, $primaryLocation);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist));

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('booking.id'),
            'provider_id' => $provider->id,
            'location_id' => $primaryLocation->id,
        ]);
        $this->assertDatabaseMissing('bookings', [
            'id' => $response->json('booking.id'),
            'location_id' => $secondaryLocation->id,
        ]);
    }

    /** 4. A location belonging to a different provider is rejected. */
    public function test_foreign_provider_location_is_rejected(): void
    {
        [$provider, $location] = $this->makeProviderWithLocation(primary: true);
        $service = $this->makeServiceAvailableAt($provider, $location);
        $specialist = $this->makeSpecialistAt($provider, $location);

        [$otherProvider, $foreignLocation] = $this->makeProviderWithLocation(primary: true);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist, [
            'location_id' => $foreignLocation->id,
        ]));

        $response->assertStatus(403);
    }

    /** 5. An inactive location is rejected. */
    public function test_inactive_location_is_rejected(): void
    {
        [$provider, $location] = $this->makeProviderWithLocation(primary: true);
        $service = $this->makeServiceAvailableAt($provider, $location);
        $specialist = $this->makeSpecialistAt($provider, $location);

        $location->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist, [
            'location_id' => $location->id,
        ]));

        $response->assertStatus(403);
    }

    /** 6. A specialist scoped to another location is not bookable here. */
    public function test_specialist_not_available_at_location_is_rejected(): void
    {
        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);

        $locationA = Location::factory()->primary()->create(['provider_id' => $provider->id]);
        $locationB = Location::factory()->create(['provider_id' => $provider->id]);

        $service = $this->makeServiceAvailableAt($provider, $locationA);

        // Specialist is only assigned at location B.
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $locationB->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
            'status' => 'ACTIVE',
        ]);
        $this->insertSchedules($provider, $locationB, $assignment);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist, [
            'location_id' => $locationA->id,
        ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('not available at the selected location', $response->json('message'));
    }

    /** 7. A salon with no location must not produce a location-less booking. */
    public function test_salon_without_location_is_rejected(): void
    {
        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30, 'price' => 50000]);

        $response = $this->postJson('/api/v1/bookings/with-account', [
            'provider_id' => $provider->id,
            'customer_name' => 'No Branch',
            'customer_phone' => '+256700111222',
            'service_id' => [$service->id],
            'date' => $this->testDate->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('location is required', $response->json('message'));
    }

    /** 8. The resolved booking is visible to the owner's location-scoped query. */
    public function test_resolved_booking_appears_in_location_scoped_query(): void
    {
        [$provider, $location] = $this->makeProviderWithLocation(primary: true);
        $service = $this->makeServiceAvailableAt($provider, $location);
        $specialist = $this->makeSpecialistAt($provider, $location);

        $response = $this->postJson('/api/v1/bookings/with-account', $this->payload($provider, $service, $specialist));
        $response->assertStatus(201);

        // Mirrors BookingController::index when location_scope = 'specific'.
        $visible = Booking::where('provider_id', $provider->id)
            ->where('location_id', $location->id)
            ->pluck('id')
            ->all();

        $this->assertContains($response->json('booking.id'), $visible);
    }
}
