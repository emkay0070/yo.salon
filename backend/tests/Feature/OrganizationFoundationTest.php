<?php

namespace Tests\Feature;

use App\Domain\Specialist\Services\IndependentWorkspaceProvisioner;
use App\Models\BusinessCategory;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PortalAccount;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\Specialist;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure canonical ProviderTypes exist in test database
        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        ProviderType::firstOrCreate(
            ['key' => 'independent_specialist'],
            ['name' => 'Independent Specialist', 'is_active' => true, 'is_independent' => true, 'is_owner_managed' => true]
        );

        ProviderType::firstOrCreate(
            ['key' => 'spa'],
            ['name' => 'Spa', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );
    }

    public function test_business_owner_onboarding_creates_business_organization_and_membership(): void
    {
        $user = User::factory()->create(['status' => 'registered']);
        $category = BusinessCategory::firstOrCreate(
            ['slug' => 'salon'],
            ['name' => 'Salon', 'provider_type' => 'salon', 'is_active' => true]
        );

        $plan = \App\Models\Plan::create([
            'name'          => 'Starter Plan',
            'slug'          => 'starter-plan-' . uniqid(),
            'monthly_price' => 29.00,
            'is_active'     => true,
        ]);

        $onboardingService = app(OnboardingService::class);
        $provider = $onboardingService->complete($user, [
            'business' => [
                'name'     => 'Elite Hair Studio',
                'category' => 'salon',
                'email'    => 'owner@elitehair.com',
                'phone'    => '+1234567890',
                'address'  => '123 Main St, Kampala',
                'opening_hours' => [
                    'monday'    => ['open' => '09:00', 'close' => '18:00'],
                    'tuesday'   => ['open' => '09:00', 'close' => '18:00'],
                    'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                    'thursday'  => ['open' => '09:00', 'close' => '18:00'],
                    'friday'    => ['open' => '09:00', 'close' => '18:00'],
                    'saturday'  => ['open' => '10:00', 'close' => '16:00'],
                    'sunday'    => ['open' => '10:00', 'close' => '14:00'],
                ],
            ],
            'membership' => [
                'plan_id' => $plan->id,
            ],
        ]);

        // 1. Provider exists and has organization_id
        $this->assertNotNull($provider->organization_id);
        
        // 2. Organization exists, type is business
        $org = $provider->organization;
        $this->assertNotNull($org);
        $this->assertEquals('Elite Hair Studio', $org->name);
        $this->assertEquals(Organization::TYPE_BUSINESS, $org->type);
        $this->assertEquals($user->id, $org->owner_user_id);

        // 3. User has org_owner membership
        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(OrganizationMembership::ROLE_OWNER, $membership->role);
        $this->assertEquals(OrganizationMembership::STATUS_ACTIVE, $membership->status);

        // 4. ProviderUser operational assignment remains distinct
        $providerUser = ProviderUser::where('provider_id', $provider->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($providerUser);
        $this->assertEquals('owner', $providerUser->role);

        // 5. Subscription is owned by Organization
        $subscription = $provider->subscription;
        if ($subscription) {
            $this->assertEquals($org->id, $subscription->organization_id);
        }
    }

    public function test_independent_specialist_receives_lightweight_personal_organization(): void
    {
        $specialist = Specialist::create([
            'name'   => 'Jane Doe Stylist',
            'email'  => 'jane@stylist.com',
            'phone'  => '+256700000001',
            'active' => true,
        ]);

        $provisioner = app(IndependentWorkspaceProvisioner::class);
        $provisioner->ensureIndependentWorkspace($specialist);

        $specialist->refresh();
        $provider = $specialist->provider;

        $this->assertNotNull($provider);
        $this->assertEquals('independent_specialist', $provider->providerTypeKey());

        // Organization exists and is of type personal
        $this->assertNotNull($provider->organization_id);
        $org = $provider->organization;
        $this->assertNotNull($org);
        $this->assertEquals(Organization::TYPE_PERSONAL, $org->type);
        $this->assertEquals('Jane Doe Stylist', $org->name);
        $this->assertTrue($org->isPersonal());
    }

    public function test_multiple_providers_can_belong_to_one_organization(): void
    {
        $user = User::factory()->create();
        $orgService = app(OrganizationService::class);

        $org = $orgService->createForBusinessOwner($user, [
            'name' => 'City Salon Group',
        ]);

        $type = ProviderType::byKey('salon')->first();

        $provider1 = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Downtown Branch',
            'slug'             => 'downtown-branch-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $provider2 = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Uptown Branch',
            'slug'             => 'uptown-branch-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $this->assertEquals($org->id, $provider1->refresh()->organization_id);
        $this->assertEquals($org->id, $provider2->refresh()->organization_id);
        $this->assertCount(2, $org->providers);
    }

    public function test_staff_joining_organization_as_member(): void
    {
        $owner = User::factory()->create();
        $staffUser = User::factory()->create();
        $orgService = app(OrganizationService::class);

        $org = $orgService->createForBusinessOwner($owner, ['name' => 'Apex Salon Group']);

        $type = ProviderType::byKey('salon')->first();
        $provider = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Apex Salon',
            'slug'             => 'apex-salon-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        // Add staff to Organization as org_member
        $membership = $orgService->addMember($org, $staffUser, OrganizationMembership::ROLE_MEMBER);
        $this->assertEquals(OrganizationMembership::ROLE_MEMBER, $membership->role);

        // Establish operational assignment on Provider as staff
        $staffUser->providers()->attach($provider->id, [
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'role'            => 'staff',
            'authority_scope' => 'location_scoped',
        ]);
        $providerUser = ProviderUser::where('user_id', $staffUser->id)
            ->where('provider_id', $provider->id)
            ->first();

        // Organization membership is org_member, ProviderUser role is staff
        $this->assertEquals(OrganizationMembership::ROLE_MEMBER, $membership->role);
        $this->assertEquals('staff', $providerUser->role);
        $this->assertTrue($org->hasMember($staffUser));
        $this->assertFalse($org->hasAdmin($staffUser));
    }

    public function test_customer_registration_does_not_create_organization(): void
    {
        $orgCountBefore = Organization::count();

        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Alice Customer',
            'email'    => 'alice@client.com',
            'password' => 'secret12345',
            'journey'  => 'customer',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['email' => 'alice@client.com']);
        $this->assertDatabaseHas('portal_accounts', ['email' => 'alice@client.com']);

        // Organizations count remains completely unchanged
        $this->assertEquals($orgCountBefore, Organization::count());
    }

    public function test_organization_authorization_boundaries(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $org = Organization::create([
            'name'          => 'Beauty Empire',
            'slug'          => 'beauty-empire',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $owner->id,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $owner->id,
            'role'            => OrganizationMembership::ROLE_OWNER,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $admin->id,
            'role'            => OrganizationMembership::ROLE_ADMIN,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $member->id,
            'role'            => OrganizationMembership::ROLE_MEMBER,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $this->assertTrue($org->hasOwner($owner));
        $this->assertFalse($org->hasOwner($admin));
        $this->assertFalse($org->hasOwner($member));

        $this->assertTrue($org->hasAdmin($owner));
        $this->assertTrue($org->hasAdmin($admin));
        $this->assertFalse($org->hasAdmin($member));

        $this->assertTrue($org->hasMember($member));
    }

    public function test_subscription_billing_ownership_boundary(): void
    {
        $owner = User::factory()->create();
        $org = app(OrganizationService::class)->createForBusinessOwner($owner, ['name' => 'Grand Salon Group']);

        $type = ProviderType::byKey('salon')->first();

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Grand Salon',
            'slug'             => 'grand-salon-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $plan = \App\Models\Plan::create([
            'name'          => 'Pro Plan',
            'slug'          => 'pro-plan-' . uniqid(),
            'monthly_price' => 99.00,
            'is_active'     => true,
        ]);

        $subscription = Subscription::create([
            'organization_id' => $org->id,
            'provider_id'     => $provider->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'starts_at'       => now(),
        ]);

        // Commercial subscription belongs to Organization
        $this->assertEquals($org->id, $subscription->organization->id);
        $this->assertCount(1, $org->subscriptions);

        // Operational Provider has the primary relationship to bookings
        $this->assertEquals($org->id, $provider->organization->id);
    }

    public function test_organization_api_endpoints(): void
    {
        $owner = User::factory()->create();
        $org = app(OrganizationService::class)->createForBusinessOwner($owner, ['name' => 'Prestige Salon Group']);

        $type = ProviderType::byKey('salon')->first();

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Prestige Salon',
            'slug'             => 'prestige-salon-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        // Create primary location
        Location::create([
            'provider_id' => $provider->id,
            'name'        => 'Main Branch',
            'slug'        => 'main-branch-' . uniqid(),
            'is_primary'  => true,
            'is_active'   => true,
        ]);

        $owner->providers()->attach($provider->id, [
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'role'            => 'owner',
            'authority_scope' => 'provider_wide',
        ]);

        // 1. GET /api/v1/organization
        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organization');

        $response->assertStatus(200)
            ->assertJsonPath('organization.id', $org->id)
            ->assertJsonPath('context.is_owner', true);

        // 2. GET /api/v1/organization/providers
        $providersResponse = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organization/providers');

        $providersResponse->assertStatus(200)
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonCount(1, 'providers');

        // 3. GET /api/v1/organization/members
        $membersResponse = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/organization/members');

        $membersResponse->assertStatus(200)
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonCount(1, 'members');

        // 4. POST /api/v1/organization/members (add member)
        $newStaff = User::factory()->create(['email' => 'staff@prestige.com']);
        $addResponse = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organization/members', [
                'email' => 'staff@prestige.com',
                'role'  => 'org_member',
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('membership.user.email', 'staff@prestige.com');

        // 5. Non-admin member cannot manage members
        $nonAdminUser = User::factory()->create();
        $nonAdminUser->providers()->attach($provider->id, [
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'role'            => 'staff',
            'authority_scope' => 'location_scoped',
        ]);
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $nonAdminUser->id,
            'role'            => OrganizationMembership::ROLE_MEMBER,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $forbiddenResponse = $this->actingAs($nonAdminUser, 'sanctum')
            ->getJson('/api/v1/organization/members');

        $forbiddenResponse->assertStatus(403);
    }
}
