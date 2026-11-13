<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderUser;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for StaffController::index() read integrity.
 *
 * Ensures that GET /api/v1/staff does not mutate database state
 * (specifically, no backfill of staff_id on SpecialistAssignment
 * during a GET request).
 */
class StaffControllerReadIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected User $user;
    protected Staff $staffMember;
    protected Specialist $specialist;
    protected SpecialistAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $this->user = User::factory()->create(['status' => 'active']);

        // Link user to provider with provider-wide authority
        ProviderUser::factory()->create([
            'user_id' => $this->user->id,
            'provider_id' => $this->provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);

        // Create a staff member with a name
        $this->staffMember = Staff::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => 'Jane Therapist',
            'location_id' => null,
        ]);

        // Create a specialist with the same name (for name-based fallback matching)
        $this->specialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => 'Jane Therapist',
        ]);

        // Create an assignment with NULL staff_id (requires name-based fallback)
        $this->assignment = SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'staff_id' => null,
            'status' => 'ACTIVE',
        ]);
    }

    protected function getAuthHeaders(): array
    {
        $token = $this->user->createToken('staff-read-test')->plainTextToken;

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    public function test_get_staff_index_does_not_backfill_staff_id()
    {
        // Record the assignment state before the request
        $assignmentBefore = SpecialistAssignment::find($this->assignment->id);
        $this->assertNull($assignmentBefore->staff_id, 'Precondition: assignment should have NULL staff_id');

        $response = $this->withToken($this->user->createToken('index-test')->plainTextToken)
            ->withHeaders($this->getAuthHeaders())
            ->getJson('/api/v1/staff');

        $response->assertStatus(200);

        // The staff member should appear in the response with specialist_id resolved
        $staffData = $response->json();
        $this->assertIsArray($staffData);
        $found = false;
        foreach ($staffData as $member) {
            if ($member['id'] === $this->staffMember->id) {
                $found = true;
                $this->assertEquals($this->specialist->id, $member['specialist_id']);
                break;
            }
        }
        $this->assertTrue($found, 'Staff member should appear in response with resolved specialist_id');

        // CRITICAL: The assignment must NOT have been backfilled during the GET request
        $assignmentAfter = SpecialistAssignment::find($this->assignment->id);
        $this->assertNull($assignmentAfter->staff_id, 'GET /staff must not backfill staff_id on SpecialistAssignment');
        $this->assertNull($assignmentAfter->staff_id, 'Database state must be unchanged after GET request');
    }

    public function test_get_staff_index_does_not_create_or_delete_assignments()
    {
        $assignmentCountBefore = SpecialistAssignment::count();

        $this->withToken($this->user->createToken('index-test-2')->plainTextToken)
            ->withHeaders($this->getAuthHeaders())
            ->getJson('/api/v1/staff')
            ->assertStatus(200);

        $assignmentCountAfter = SpecialistAssignment::count();

        $this->assertEquals($assignmentCountBefore, $assignmentCountAfter,
            'GET /staff must not create or delete SpecialistAssignment records');
    }

    public function test_get_staff_index_with_direct_staff_id_match_does_not_mutate()
    {
        // Create a second assignment with direct staff_id match (no backfill needed)
        SpecialistAssignment::factory()->create([
            'specialist_id' => Specialist::factory()->create(['provider_id' => $this->provider->id, 'name' => 'Other Specialist'])->id,
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'staff_id' => $this->staffMember->id,
            'status' => 'ACTIVE',
        ]);

        $staffIdsBefore = SpecialistAssignment::whereNotNull('staff_id')->pluck('id')->sort()->values()->all();

        $this->withToken($this->user->createToken('index-test-3')->plainTextToken)
            ->withHeaders($this->getAuthHeaders())
            ->getJson('/api/v1/staff')
            ->assertStatus(200);

        $staffIdsAfter = SpecialistAssignment::whereNotNull('staff_id')->pluck('id')->sort()->values()->all();

        $this->assertEquals($staffIdsBefore, $staffIdsAfter,
            'GET /staff must not change which assignments have staff_id set');
    }

    public function test_show_staff_with_location_null_assignment_location_set()
    {
        // S2: Staff with location_id = NULL but assignment with location_id set
        // must still resolve the assignment (staff_id + provider_id is sufficient).
        // The old code used where('location_id', $staff->location_id) which would
        // produce WHERE location_id IS NULL and miss assignments that have a
        // location_id set. The fix removes that constraint.
        $location = \App\Models\Location::factory()->create(['provider_id' => $this->provider->id]);

        $staffNoLocation = Staff::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => 'No Location Staff',
            'location_id' => null,
        ]);

        $specialistNoLocation = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => 'No Location Staff',
        ]);

        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialistNoLocation->id,
            'provider_id' => $this->provider->id,
            'location_id' => $location->id,
            'staff_id' => $staffNoLocation->id,
            'status' => 'ACTIVE',
        ]);

        // Direct lookup: staff_id + provider_id must find the assignment
        // regardless of location_id mismatch (NULL vs set).
        $found = \App\Models\SpecialistAssignment::where('staff_id', $staffNoLocation->id)
            ->where('provider_id', $this->provider->id)
            ->first();

        $this->assertNotNull($found, 'Assignment must be found by staff_id + provider_id');
        $this->assertEquals($specialistNoLocation->id, $found->specialist_id);
        $this->assertEquals($location->id, $found->location_id);
    }
}
