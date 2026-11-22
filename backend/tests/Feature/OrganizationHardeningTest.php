<?php

namespace Tests\Feature;

use App\Domain\Specialist\Services\IndependentWorkspaceProvisioner;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\SubscriptionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        ProviderType::firstOrCreate(
            ['key' => 'independent_specialist'],
            ['name' => 'Independent Specialist', 'is_active' => true, 'is_independent' => true, 'is_owner_managed' => true]
        );
    }

    /**
     * Helper to create a SpecialistAccount.
     */
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

    // =========================================================================
    // 1. Dual-Principal Membership & Ownership Invariants
    // =========================================================================

    public function test_user_and_specialist_account_can_both_be_designated_owners(): void
    {
        $user = User::factory()->create();
        $specialist = $this->createSpecialistAccount('Dr. Nail Specialist');

        // Business org owned by User
        $userOrg = Organization::create([
            'name'          => 'User Salon Group',
            'slug'          => 'user-salon-group',
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'owner_user_id' => $user->id,
        ]);

        // Personal org owned by SpecialistAccount
        $specOrg = Organization::create([
            'name'                        => 'Specialist Studio',
            'slug'                        => 'specialist-studio',
            'type'                        => Organization::TYPE_PERSONAL,
            'status'                      => Organization::STATUS_ACTIVE,
            'owner_specialist_account_id' => $specialist->id,
        ]);

        $this->assertTrue($userOrg->isUserOwned());
        $this->assertFalse($userOrg->isSpecialistOwned());
        $this->assertEquals($user->id, $userOrg->owner_principal->id);

        $this->assertTrue($specOrg->isSpecialistOwned());
        $this->assertFalse($specOrg->isUserOwned());
        $this->assertEquals($specialist->id, $specOrg->owner_principal->id);
    }

    public function test_db_rejects_organization_with_both_owner_columns_populated(): void
    {
        $user = User::factory()->create();
        $specialist = $this->createSpecialistAccount();

        $this->expectException(QueryException::class);

        Organization::create([
            'name'                        => 'Invalid Dual Owner Org',
            'slug'                        => 'invalid-dual-owner-org',
            'type'                        => Organization::TYPE_BUSINESS,
            'status'                      => Organization::STATUS_ACTIVE,
            'owner_user_id'               => $user->id,
            'owner_specialist_account_id' => $specialist->id,
        ]);
    }

    public function test_db_rejects_duplicate_user_membership_in_same_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'name'          => 'Unique Test Org',
            'slug'          => 'unique-test-org',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'role'            => OrganizationMembership::ROLE_OWNER,
        ]);

        $this->expectException(QueryException::class);

        // Duplicate insert for same (organization_id, user_id)
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'role'            => OrganizationMembership::ROLE_MEMBER,
        ]);
    }

    public function test_db_rejects_duplicate_specialist_membership_in_same_organization(): void
    {
        $spec = $this->createSpecialistAccount();
        $org = Organization::create([
            'name'                        => 'Unique Spec Org',
            'slug'                        => 'unique-spec-org',
            'type'                        => Organization::TYPE_PERSONAL,
            'owner_specialist_account_id' => $spec->id,
        ]);

        OrganizationMembership::create([
            'organization_id'       => $org->id,
            'specialist_account_id' => $spec->id,
            'role'                  => OrganizationMembership::ROLE_OWNER,
        ]);

        $this->expectException(QueryException::class);

        // Duplicate insert for same (organization_id, specialist_account_id)
        OrganizationMembership::create([
            'organization_id'       => $org->id,
            'specialist_account_id' => $spec->id,
            'role'                  => OrganizationMembership::ROLE_MEMBER,
        ]);
    }

    // =========================================================================
    // 2. Independent Specialist Provisioning
    // =========================================================================

    public function test_independent_specialist_provisioning_assigns_personal_org_without_duplicate_user(): void
    {
        $specialistAccount = $this->createSpecialistAccount('Independent Glow', 'glow@independent.com');
        $specialist = $specialistAccount->specialist;

        $provisioner = app(IndependentWorkspaceProvisioner::class);
        $provisioner->ensureIndependentWorkspace($specialist);

        $specialist->refresh();
        $provider = $specialist->provider;

        $this->assertNotNull($provider);
        $this->assertNotNull($provider->organization_id);

        $org = $provider->organization;
        $this->assertNotNull($org);
        $this->assertEquals(Organization::TYPE_PERSONAL, $org->type);
        $this->assertEquals($specialistAccount->id, $org->owner_specialist_account_id);
        $this->assertNull($org->owner_user_id);

        // Verify NO duplicate user was created
        $this->assertNull(User::where('email', 'glow@independent.com')->first());

        // Verify SpecialistAccount has org_owner membership
        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where('specialist_account_id', $specialistAccount->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(OrganizationMembership::ROLE_OWNER, $membership->role);
        $this->assertTrue($org->hasOwner($specialistAccount));
    }

    // =========================================================================
    // 3. Mandatory Provider Ownership & Deletion Protection
    // =========================================================================

    public function test_provider_requires_organization_id(): void
    {
        $this->expectException(QueryException::class);

        $type = ProviderType::byKey('salon')->first();

        // Direct DB insert without organization_id must fail
        DB::table('providers')->insert([
            'id'               => (string) Str::uuid(),
            'organization_id'  => null,
            'provider_type_id' => $type->id,
            'display_name'     => 'Orphan Provider',
            'slug'             => 'orphan-provider',
            'status'           => 'active',
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_organization_deletion_restricted_when_providers_exist(): void
    {
        $user = User::factory()->create();
        $org = app(OrganizationService::class)->createForBusinessOwner($user, ['name' => 'Protected Org']);

        $type = ProviderType::byKey('salon')->first();
        Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Protected Salon',
            'slug'             => 'protected-salon',
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $this->expectException(QueryException::class);

        // Force-deleting the organization record from DB must be restricted by FK
        DB::table('organizations')->where('id', $org->id)->delete();
    }

    // =========================================================================
    // 4. Commercial Subscription Ownership
    // =========================================================================

    public function test_subscription_authority_belongs_to_organization(): void
    {
        $user = User::factory()->create();
        $org = app(OrganizationService::class)->createForBusinessOwner($user, ['name' => 'SaaS Billing Org']);

        $plan = Plan::create([
            'name'          => 'Enterprise Tier',
            'slug'          => 'enterprise-tier-' . uniqid(),
            'monthly_price' => 199.00,
            'is_active'     => true,
        ]);

        $subService = app(SubscriptionService::class);
        $subscription = $subService->startTrial($org->id, $plan->id, 14);

        $this->assertEquals($org->id, $subscription->organization_id);
        $this->assertEquals('trialing', $subscription->status);

        // Lookup by organization returns active subscription
        $found = $subService->getSubscriptionByOrganization($org->id);
        $this->assertNotNull($found);
        $this->assertEquals($subscription->id, $found->id);
    }

    // =========================================================================
    // 5. Ownership Transfer
    // =========================================================================

    public function test_ownership_transfer_between_users(): void
    {
        $owner = User::factory()->create();
        $successor = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $org = $orgService->createForBusinessOwner($owner, ['name' => 'Transferable Org']);

        // Successor must be an active member first
        $orgService->addMember($org, $successor, OrganizationMembership::ROLE_ADMIN);

        // Execute transfer
        $orgService->transferOwnership($org, $owner, $successor);

        $org->refresh();
        $this->assertEquals($successor->id, $org->owner_user_id);
        $this->assertNull($org->owner_specialist_account_id);

        // Successor is now org_owner
        $successorMembership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $successor->id)
            ->first();
        $this->assertEquals(OrganizationMembership::ROLE_OWNER, $successorMembership->role);

        // Former owner is demoted to org_admin
        $formerMembership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $owner->id)
            ->first();
        $this->assertEquals(OrganizationMembership::ROLE_ADMIN, $formerMembership->role);
    }

    public function test_ownership_transfer_from_user_to_specialist_account(): void
    {
        $owner = User::factory()->create();
        $specialistAccount = $this->createSpecialistAccount('Successor Specialist');

        $orgService = app(OrganizationService::class);
        $org = $orgService->createForBusinessOwner($owner, ['name' => 'Hybrid Org']);

        // Specialist must be an active member first
        $orgService->addMember($org, $specialistAccount, OrganizationMembership::ROLE_ADMIN);

        // Execute transfer from User to SpecialistAccount
        $orgService->transferOwnership($org, $owner, $specialistAccount);

        $org->refresh();
        $this->assertNull($org->owner_user_id);
        $this->assertEquals($specialistAccount->id, $org->owner_specialist_account_id);
        $this->assertTrue($org->isSpecialistOwned());

        $specMembership = OrganizationMembership::where('organization_id', $org->id)
            ->where('specialist_account_id', $specialistAccount->id)
            ->first();
        $this->assertEquals(OrganizationMembership::ROLE_OWNER, $specMembership->role);
    }

    public function test_ownership_transfer_fails_if_not_current_owner(): void
    {
        $owner = User::factory()->create();
        $imposter = User::factory()->create();
        $target = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $org = $orgService->createForBusinessOwner($owner, ['name' => 'Secure Org']);
        $orgService->addMember($org, $imposter, OrganizationMembership::ROLE_ADMIN);
        $orgService->addMember($org, $target, OrganizationMembership::ROLE_MEMBER);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current owner verification failed.');

        // Imposter tries to transfer ownership
        $orgService->transferOwnership($org, $imposter, $target);
    }

    public function test_ownership_transfer_fails_if_target_is_not_active_member(): void
    {
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $org = $orgService->createForBusinessOwner($owner, ['name' => 'Closed Org']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('New owner must be an active member of the Organization.');

        // Trying to transfer to non-member
        $orgService->transferOwnership($org, $owner, $nonMember);
    }

    // =========================================================================
    // 6. Organization Context Middleware Resolution
    // =========================================================================

    public function test_explicit_header_with_unauthorized_org_returns_403(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $userOrg = $orgService->createForBusinessOwner($user, ['name' => 'User Org']);
        $foreignOrg = $orgService->createForBusinessOwner($otherUser, ['name' => 'Foreign Org']);

        // User attempts to access their own endpoint with foreign organization header
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', $foreignOrg->id)
            ->getJson('/api/v1/organization');

        $response->assertStatus(403);
    }

    public function test_multiple_org_memberships_without_header_returns_409(): void
    {
        $user = User::factory()->create();
        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $org1 = $orgService->createForBusinessOwner($otherUser1, ['name' => 'First Org']);
        $org2 = $orgService->createForBusinessOwner($otherUser2, ['name' => 'Second Org']);

        // User is added as member to two organizations
        $orgService->addMember($org1, $user, OrganizationMembership::ROLE_MEMBER);
        $orgService->addMember($org2, $user, OrganizationMembership::ROLE_MEMBER);

        // Accessing without X-Organization-Id header must return 409 Conflict
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organization');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Multiple organizations found. Organization context required (X-Organization-Id header).');

        // Providing explicit header succeeds
        $successResponse = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', $org1->id)
            ->getJson('/api/v1/organization');

        $successResponse->assertStatus(200)
            ->assertJsonPath('organization.id', $org1->id);
    }

    public function test_specialist_account_can_access_organization_api_via_specialist_guard(): void
    {
        $specialistAccount = $this->createSpecialistAccount('Api Specialist', 'api.spec@example.com');
        $org = app(OrganizationService::class)->createPersonalForSpecialistPrincipal($specialistAccount, [
            'name' => 'Api Specialist Practice',
        ]);

        $response = $this->actingAs($specialistAccount, 'specialist')
            ->getJson('/api/v1/organization');

        $response->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id)
            ->assertJsonPath('context.is_owner', true);
    }

    public function test_ownership_transfer_api_endpoint(): void
    {
        $owner = User::factory()->create();
        $successor = User::factory()->create();

        $orgService = app(OrganizationService::class);
        $org = $orgService->createForBusinessOwner($owner, ['name' => 'API Transfer Org']);
        $orgService->addMember($org, $successor, OrganizationMembership::ROLE_ADMIN);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organization/transfer-ownership', [
                'new_owner_user_id' => $successor->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('organization.owner_user_id', $successor->id);

        $this->assertEquals($successor->id, $org->fresh()->owner_user_id);
    }
}
