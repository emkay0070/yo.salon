<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Location;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignableSpecialistsTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $locationA;
    private Location $locationB;
    private Specialist $specialistWithActiveAssignment;
    private Specialist $danglingSpecialist;
    private Specialist $locationASpecialist;
    private Specialist $providerWideSpecialist;
    private Specialist $inactiveAssignmentSpecialist;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup provider with two locations
        $this->provider = Provider::factory()->create();
        $this->locationA = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->locationB = Location::factory()->create(['provider_id' => $this->provider->id]);

        // TC-1: Specialist with active assignment (location-specific)
        $this->specialistWithActiveAssignment = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'active' => true,
        ]);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialistWithActiveAssignment->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->locationA->id,
            'status' => 'ACTIVE',
        ]);

        // TC-2: Dangling specialist (active but no assignment)
        $this->danglingSpecialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'active' => true,
        ]);
        // No SpecialistAssignment created

        // TC-3: Specialist assigned to Location A only
        $this->locationASpecialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'active' => true,
        ]);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->locationASpecialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->locationA->id,
            'status' => 'ACTIVE',
        ]);

        // TC-4: Provider-wide specialist (location_id = NULL)
        $this->providerWideSpecialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'active' => true,
        ]);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->providerWideSpecialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'status' => 'ACTIVE',
        ]);

        // TC-5: Specialist with inactive assignment
        $this->inactiveAssignmentSpecialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'active' => true,
        ]);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->inactiveAssignmentSpecialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->locationA->id,
            'status' => 'INACTIVE',
        ]);
    }

    /** TC-1: Active assigned specialist should be included in provider-wide query */
    public function test_active_assigned_specialist_included_in_provider_wide_query(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($this->specialistWithActiveAssignment->id, $specialistIds);
    }

    /** TC-2: Dangling specialist (no assignment) should be excluded */
    public function test_dangling_specialist_excluded(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertNotContains($this->danglingSpecialist->id, $specialistIds);
    }

    /** TC-3: Specialist assigned to Location A should be excluded from Location B query */
    public function test_location_a_specialist_excluded_from_location_b_query(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists?location_id={$this->locationB->id}");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertNotContains($this->locationASpecialist->id, $specialistIds);
    }

    /** TC-4: Provider-wide assignment (location_id=NULL) should be excluded from location-specific query */
    public function test_provider_wide_assignment_excluded_from_location_specific_query(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists?location_id={$this->locationA->id}");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertNotContains($this->providerWideSpecialist->id, $specialistIds);
    }

    /** TC-5: Provider-wide assignment should be included in provider-wide query */
    public function test_provider_wide_assignment_included_in_provider_wide_query(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($this->providerWideSpecialist->id, $specialistIds);
    }

    /** TC-6: Inactive assignment should be excluded */
    public function test_inactive_assignment_excluded(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertNotContains($this->inactiveAssignmentSpecialist->id, $specialistIds);
    }

    /** TC-7: Location-specific query should only include specialists assigned to that location */
    public function test_location_specific_query_only_includes_assigned_specialists(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists?location_id={$this->locationA->id}");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();

        // Should include specialistWithActiveAssignment (assigned to locationA)
        $this->assertContains($this->specialistWithActiveAssignment->id, $specialistIds);
        // Should include locationASpecialist (assigned to locationA)
        $this->assertContains($this->locationASpecialist->id, $specialistIds);

        // Should NOT include providerWideSpecialist (location_id = NULL)
        $this->assertNotContains($this->providerWideSpecialist->id, $specialistIds);
        // Should NOT include danglingSpecialist (no assignment)
        $this->assertNotContains($this->danglingSpecialist->id, $specialistIds);
        // Should NOT include inactiveAssignmentSpecialist (inactive status)
        $this->assertNotContains($this->inactiveAssignmentSpecialist->id, $specialistIds);
    }

    /** TC-8: Provider-wide query should include both location-specific and provider-wide assignments */
    public function test_provider_wide_query_includes_all_active_assignments(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->id}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();

        // Should include specialistWithActiveAssignment (location-specific)
        $this->assertContains($this->specialistWithActiveAssignment->id, $specialistIds);
        // Should include locationASpecialist (location-specific)
        $this->assertContains($this->locationASpecialist->id, $specialistIds);
        // Should include providerWideSpecialist (provider-wide)
        $this->assertContains($this->providerWideSpecialist->id, $specialistIds);

        // Should NOT include danglingSpecialist (no assignment)
        $this->assertNotContains($this->danglingSpecialist->id, $specialistIds);
        // Should NOT include inactiveAssignmentSpecialist (inactive status)
        $this->assertNotContains($this->inactiveAssignmentSpecialist->id, $specialistIds);
    }

    /** TC-9: Endpoint works with provider slug instead of UUID */
    public function test_endpoint_works_with_provider_slug(): void
    {
        $response = $this->getJson("/api/v1/providers/{$this->provider->slug}/assignable-specialists");

        $response->assertStatus(200);
        $specialistIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($this->specialistWithActiveAssignment->id, $specialistIds);
    }
}
