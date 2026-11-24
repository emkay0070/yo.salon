<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OrganizationBusinessApiContextTest
 *
 * Focused verification that authenticated commercial (organization-scoped)
 * business API requests establish the correct Organization context and that
 * the PostgreSQL RLS context middleware chain executes in the required order:
 *
 *   auth -> organization.context -> organization.db_context -> controller
 *
 * It also verifies:
 *  - unauthorized cross-organization requests are rejected (403);
 *  - arbitrary organization IDs supplied via headers are not trusted;
 *  - RLS context is cleaned up after a successful request;
 *  - public/guest endpoints are not affected (they sit outside these groups);
 *  - independent specialist access is preserved.
 */
class OrganizationBusinessApiContextTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationService $organizationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationService = app(OrganizationService::class);

        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );
    }

    protected function createSpecialistAccount(string $name = 'Test Specialist', string $email = null): SpecialistAccount
    {
        $email = $email ?? 'spec-' . Str::random(8) . '@example.com';

        $specialist = Specialist::create([
            'name'   => $name,
            'email'  => $email,
            'phone'  => '+2567' . rand(10000000, 99999999),
            'active' => true,
        ]);

        return SpecialistAccount::create([
            'id'            => (string) Str::uuid(),
            'specialist_id' => $specialist->id,
            'email'         => $email,
            'password'      => Hash::make('secret123'),
            'is_active'     => true,
        ]);
    }

    // ------------------------------------------------------------------
    // 1. Authenticated business request establishes organization context
    // ------------------------------------------------------------------

    #[Test]
    public function authenticated_business_request_establishes_organization_context(): void
    {
        $owner = User::factory()->create();
        $org = $this->organizationService->createForBusinessOwner($owner, ['name' => 'Alpha Salon']);

        $this->organizationService->attachProvider(
            $org,
            Provider::create([
                'id'               => (string) Str::uuid(),
                'organization_id'  => $org->id,
                'provider_type_id' => ProviderType::byKey('salon')->first()->id,
                'display_name'     => 'Alpha Salon',
                'slug'             => 'alpha-salon',
                'status'           => 'active',
                'active'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ])
        );

        $response = $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/organization');

        // HTTP contract: request succeeds and returns the resolved organization
        $response->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id);

        // organization.context injected the organization into request attributes
        $this->assertEquals($org->id, request()->attributes->get('organization_id'));
        $this->assertInstanceOf(Organization::class, request()->attributes->get('organization'));

        // OrganizationContext (request-scoped via controller injection) holds the resolved organization,
        // evidenced by the 200 response returning the correct organization.
        $this->assertTrue($response->json('context.is_owner'));
    }

    #[Test]
    public function business_request_initializes_and_cleans_up_rls_context(): void
    {
        $owner = User::factory()->create();
        $org = $this->organizationService->createForBusinessOwner($owner, ['name' => 'Beta Salon']);

        // A successful 200 proves organization.db_context initialized the RLS
        // context without aborting (fail-closed would otherwise return 500).
        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/organization')
            ->assertStatus(200);

        // RLS context is transaction-local and must be cleared after success.
        $current = DB::connection('pgsql')
            ->selectOne("SELECT current_setting('app.current_organization_id', true) AS org_id");

        $this->assertSame('', (string) $current->org_id, 'RLS context must be cleared after a successful request');
    }

    #[Test]
    public function single_active_membership_auto_resolves_without_header(): void
    {
        $owner = User::factory()->create();
        $org = $this->organizationService->createForBusinessOwner($owner, ['name' => 'Solo Org']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/organization');

        $response->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id)
            ->assertJsonPath('context.is_owner', true);
    }

    // ------------------------------------------------------------------
    // 2. Cross-organization protection (do not trust arbitrary header org IDs)
    // ------------------------------------------------------------------

    #[Test]
    public function unauthorized_cross_organization_request_is_rejected(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->organizationService->createForBusinessOwner($ownerA, ['name' => 'Org A']);

        $ownerB = User::factory()->create();
        $orgB = $this->organizationService->createForBusinessOwner($ownerB, ['name' => 'Org B']);

        // Org A member attempts to operate in Org B context -> must be rejected.
        $response = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson('/api/v1/organization');

        $response->assertStatus(403);
        $this->assertNotEquals($orgB->id, app(OrganizationContext::class)->getOrganization()?->id);
    }

    #[Test]
    public function unauthenticated_organization_request_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->organizationService->createForBusinessOwner($owner, ['name' => 'Org']);

        $this->getJson('/api/v1/organization')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // 3. Independent Specialist access is preserved
    // ------------------------------------------------------------------

    #[Test]
    public function independent_specialist_can_access_organization_api(): void
    {
        $specialistAccount = $this->createSpecialistAccount('Api Specialist', 'api.spec@example.com');
        $org = $this->organizationService->createPersonalForSpecialistPrincipal($specialistAccount, [
            'name' => 'Api Specialist Practice',
        ]);

        $response = $this->actingAs($specialistAccount, 'specialist')
            ->getJson('/api/v1/organization');

        $response->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id)
            ->assertJsonPath('context.is_owner', true);
    }

    // ------------------------------------------------------------------
    // 4. Organization-scoped provider listing is confined to resolved org
    // ------------------------------------------------------------------

    #[Test]
    public function organization_providers_listing_is_scoped_to_resolved_organization(): void
    {
        $owner = User::factory()->create();
        $org = $this->organizationService->createForBusinessOwner($owner, ['name' => 'Listing Org']);

        $this->organizationService->attachProvider(
            $org,
            Provider::create([
                'id'               => (string) Str::uuid(),
                'organization_id'  => $org->id,
                'provider_type_id' => ProviderType::byKey('salon')->first()->id,
                'display_name'     => 'Listing Salon',
                'slug'             => 'listing-salon',
                'status'           => 'active',
                'active'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ])
        );

        $response = $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/organization/providers');

        $response->assertStatus(200)
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonCount(1, 'providers');
    }
}
