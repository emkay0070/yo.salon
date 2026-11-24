<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderContextResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Provider $providerA;
    private Provider $providerB;
    private Provider $unauthorizedProvider;
    private Location $locationA;
    private Location $locationB;
    private Organization $organizationA;
    private Organization $organizationB;

    protected function setUp(): void
    {
        parent::setUp();

        $salonType = ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);

        // Organization A & Provider A
        $this->organizationA = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'Org A',
            'slug' => 'org-a',
            'type' => Organization::TYPE_BUSINESS,
            'status' => 'active',
            'owner_user_id' => $this->user->id,
        ]);

        OrganizationMembership::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->user->id,
            'role' => OrganizationMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        $this->providerA = Provider::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationA->id,
            'provider_type_id' => $salonType->id,
            'display_name' => 'Salon Alpha',
            'slug' => 'salon-alpha',
            'status' => 'active',
            'active' => true,
        ]);

        $this->locationA = Location::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->providerA->id,
            'name' => 'Alpha Main',
            'slug' => 'alpha-main',
            'is_active' => true,
        ]);

        $this->user->providers()->attach($this->providerA->id, [
            'id' => (string) Str::uuid(),
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
            'created_at' => now()->subDay(),
        ]);

        // Organization B & Provider B
        $this->organizationB = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'Org B',
            'slug' => 'org-b',
            'type' => Organization::TYPE_BUSINESS,
            'status' => 'active',
            'owner_user_id' => $this->user->id,
        ]);

        OrganizationMembership::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationB->id,
            'user_id' => $this->user->id,
            'role' => OrganizationMembership::ROLE_OWNER,
            'status' => 'active',
        ]);

        $this->providerB = Provider::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationB->id,
            'provider_type_id' => $salonType->id,
            'display_name' => 'Barbershop Beta',
            'slug' => 'barbershop-beta',
            'status' => 'active',
            'active' => true,
        ]);

        $this->locationB = Location::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $this->providerB->id,
            'name' => 'Beta Branch',
            'slug' => 'beta-branch',
            'is_active' => true,
        ]);

        $this->user->providers()->attach($this->providerB->id, [
            'id' => (string) Str::uuid(),
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
            'created_at' => now(),
        ]);

        // Unauthorized Provider (user does NOT belong to this)
        $this->unauthorizedProvider = Provider::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationA->id,
            'provider_type_id' => $salonType->id,
            'display_name' => 'Unauthorized Salon',
            'slug' => 'unauthorized-salon',
            'status' => 'active',
            'active' => true,
        ]);
    }

    /**
     * Test provider resolution with X-Provider-Id and X-Organization-Id header succeeds.
     */
    public function test_resolves_exact_provider_via_x_provider_id_header(): void
    {
        Sanctum::actingAs($this->user);

        // Requesting Provider A explicitly via X-Provider-Id and Organization A via X-Organization-Id
        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationA->id,
            'X-Provider-Id'     => $this->providerA->id,
        ])->getJson('/api/v1/bookings');

        if ($response->status() !== 200) {
            dump('Test 1 failed:', $response->json());
        }

        $response->assertStatus(200);
    }

    /**
     * Test multi-organization user calling business API without X-Organization-Id returns 409 Conflict.
     */
    public function test_multi_organization_without_header_returns_409(): void
    {
        Sanctum::actingAs($this->user);

        // Omitting X-Organization-Id when belonging to multiple organizations triggers 409
        $response = $this->withHeaders([
            'X-Provider-Id' => $this->providerA->id,
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Multiple organizations found. Organization context required (X-Organization-Id header).',
        ]);
    }

    /**
     * Test forged or unauthorized X-Organization-Id returns 403 Forbidden.
     */
    public function test_forged_organization_header_returns_403(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();

        $foreignOrg = Organization::create([
            'id'            => (string) Str::uuid(),
            'name'          => 'Foreign Org',
            'slug'          => 'foreign-org',
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => 'active',
            'owner_user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders([
            'X-Organization-Id' => $foreignOrg->id,
            'X-Provider-Id'     => $this->providerA->id,
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized organization context.',
        ]);
    }

    /**
     * Test requesting an unauthorized provider returns 403 Forbidden.
     */
    public function test_unauthorized_provider_header_returns_403(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationA->id,
            'X-Provider-Id'     => $this->unauthorizedProvider->id,
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You do not have access to this provider',
        ]);
    }

    /**
     * Test cross-provider location context leakage is rejected with 403.
     * Sending Provider B's ID with Location A's ID must fail because Location A belongs to Provider A.
     */
    public function test_cross_provider_location_leakage_is_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationB->id,
            'X-Provider-Id'     => $this->providerB->id,
            'X-Location-Id'     => $this->locationA->id, // Location A belongs to Provider A, NOT B!
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Location not found or does not belong to this Provider',
        ]);
    }

    /**
     * Test location belonging to the requested provider succeeds.
     */
    public function test_valid_location_for_requested_provider_succeeds(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationB->id,
            'X-Provider-Id'     => $this->providerB->id,
            'X-Location-Id'     => $this->locationB->id,
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(200);
    }

    /**
     * Test organization context propagation prevents 409 conflict when user has multiple organizations.
     */
    public function test_organization_context_header_resolves_organization(): void
    {
        Sanctum::actingAs($this->user);

        // When multiple organizations exist, X-Organization-Id must be provided to avoid 409
        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationA->id,
        ])->getJson('/api/v1/organization');

        $response->assertStatus(200);
        $this->assertEquals($this->organizationA->id, $response->json('organization.id'));
    }

    /**
     * Test cross-organization data isolation on business API via PostgreSQL RLS.
     */
    public function test_cross_organization_data_isolation_on_business_api(): void
    {
        Sanctum::actingAs($this->user);

        $customer = Customer::factory()->create();
        $serviceA = Service::factory()->create(['provider_id' => $this->providerA->id]);
        $serviceB = Service::factory()->create(['provider_id' => $this->providerB->id]);

        $bookingA = Booking::create([
            'id'             => (string) Str::uuid(),
            'provider_id'    => $this->providerA->id,
            'location_id'    => $this->locationA->id,
            'customer_id'    => $customer->id,
            'service_id'     => $serviceA->id,
            'date'           => now()->format('Y-m-d'),
            'time'           => '10:00',
            'status'         => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        $bookingB = Booking::create([
            'id'             => (string) Str::uuid(),
            'provider_id'    => $this->providerB->id,
            'location_id'    => $this->locationB->id,
            'customer_id'    => $customer->id,
            'service_id'     => $serviceB->id,
            'date'           => now()->format('Y-m-d'),
            'time'           => '11:00',
            'status'         => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        // Querying Provider A under Organization A must return ONLY Booking A
        $responseA = $this->withHeaders([
            'X-Organization-Id' => $this->organizationA->id,
            'X-Provider-Id'     => $this->providerA->id,
        ])->getJson('/api/v1/bookings');

        $responseA->assertStatus(200);
        $idsA = collect($responseA->json())->pluck('id')->all();
        $this->assertContains($bookingA->id, $idsA, 'Org A request should return Booking A');
        $this->assertNotContains($bookingB->id, $idsA, 'Org A request must NOT return Booking B');

        // Querying Provider B under Organization B must return ONLY Booking B
        $responseB = $this->withHeaders([
            'X-Organization-Id' => $this->organizationB->id,
            'X-Provider-Id'     => $this->providerB->id,
        ])->getJson('/api/v1/bookings');

        $responseB->assertStatus(200);
        $idsB = collect($responseB->json())->pluck('id')->all();
        $this->assertContains($bookingB->id, $idsB, 'Org B request should return Booking B');
        $this->assertNotContains($bookingA->id, $idsB, 'Org B request must NOT return Booking A');
    }

    /**
     * Test PostgreSQL session variable app.current_organization_id is cleanly reset after requests.
     */
    public function test_postgres_rls_session_variable_is_cleared_after_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Organization-Id' => $this->organizationA->id,
            'X-Provider-Id'     => $this->providerA->id,
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(200);

        // After request finishes, session variable must be cleared/restored to ''
        $currentOrgSetting = DB::select("SELECT current_setting('app.current_organization_id', true) as org")[0]->org;
        $this->assertEquals('', $currentOrgSetting, 'RLS session variable must be empty after request completion');
    }
}
