<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization_with_uuid(): void
    {
        $user = User::factory()->create();

        $org = Organization::create([
            'name'          => 'Luxe Beauty Group',
            'slug'          => 'luxe-beauty-group',
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'email'         => 'contact@luxebeauty.com',
            'owner_user_id' => $user->id,
        ]);

        $this->assertNotNull($org->id);
        $this->assertTrue(Str::isUuid($org->id));
        $this->assertEquals('Luxe Beauty Group', $org->name);
        $this->assertTrue($org->isBusiness());
        $this->assertFalse($org->isPersonal());
        $this->assertTrue($org->isActive());
        $this->assertTrue($org->isUserOwned());
        $this->assertFalse($org->isSpecialistOwned());
        $this->assertEquals($user->id, $org->ownerUser->id);
        $this->assertEquals($user->id, $org->owner_principal->id);
    }

    public function test_can_create_organization_owned_by_specialist_account(): void
    {
        $specialist = Specialist::create([
            'name'   => 'Solo Artist',
            'email'  => 'artist@example.com',
            'phone'  => '0700123456',
            'active' => true,
        ]);

        $specialistAccount = SpecialistAccount::create([
            'id'            => (string) Str::uuid(),
            'specialist_id' => $specialist->id,
            'email'         => $specialist->email,
            'password'      => Hash::make('secret123'),
            'is_active'     => true,
        ]);

        $org = Organization::create([
            'name'                        => 'Solo Studio',
            'slug'                        => 'solo-studio',
            'type'                        => Organization::TYPE_PERSONAL,
            'status'                      => Organization::STATUS_ACTIVE,
            'email'                       => 'artist@example.com',
            'owner_specialist_account_id' => $specialistAccount->id,
        ]);

        $this->assertNotNull($org->id);
        $this->assertTrue($org->isPersonal());
        $this->assertTrue($org->isSpecialistOwned());
        $this->assertFalse($org->isUserOwned());
        $this->assertNull($org->ownerUser);
        $this->assertEquals($specialistAccount->id, $org->ownerSpecialistAccount->id);
        $this->assertEquals($specialistAccount->id, $org->owner_principal->id);
    }

    public function test_organization_type_scopes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Organization::create([
            'name'          => 'Salon Group',
            'slug'          => 'salon-group',
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'owner_user_id' => $user1->id,
        ]);

        Organization::create([
            'name'          => 'Solo Specialist',
            'slug'          => 'solo-specialist',
            'type'          => Organization::TYPE_PERSONAL,
            'status'        => Organization::STATUS_ACTIVE,
            'owner_user_id' => $user2->id,
        ]);

        $this->assertEquals(1, Organization::business()->count());
        $this->assertEquals(1, Organization::personal()->count());
        $this->assertEquals(2, Organization::active()->count());
    }

    public function test_organization_membership_roles_and_helpers(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $org = Organization::create([
            'name'          => 'Apex Brands',
            'slug'          => 'apex-brands',
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'owner_user_id' => $owner->id,
        ]);

        $mOwner = OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $owner->id,
            'role'            => OrganizationMembership::ROLE_OWNER,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $mAdmin = OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $admin->id,
            'role'            => OrganizationMembership::ROLE_ADMIN,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $mMember = OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id'         => $member->id,
            'role'            => OrganizationMembership::ROLE_MEMBER,
            'status'          => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $this->assertTrue($mOwner->isOwner());
        $this->assertTrue($mOwner->isAdmin());
        $this->assertTrue($mOwner->isUser());
        $this->assertFalse($mOwner->isSpecialist());
        $this->assertEquals($owner->id, $mOwner->principal->id);

        $this->assertTrue($mAdmin->isAdmin());
        $this->assertFalse($mAdmin->isOwner());
        $this->assertFalse($mMember->isAdmin());
        $this->assertFalse($mMember->isOwner());

        $this->assertTrue($org->hasOwner($owner));
        $this->assertTrue($org->hasAdmin($admin));
        $this->assertTrue($org->hasAdmin($owner));
        $this->assertFalse($org->hasAdmin($member));
        $this->assertTrue($org->hasMember($member));
    }

    public function test_specialist_account_membership(): void
    {
        $specialist = Specialist::create([
            'name'   => 'Dr. Jane Skin',
            'email'  => 'jane.skin@example.com',
            'phone'  => '0700999999',
            'active' => true,
        ]);

        $account = SpecialistAccount::create([
            'id'            => (string) Str::uuid(),
            'specialist_id' => $specialist->id,
            'email'         => $specialist->email,
            'password'      => Hash::make('password123'),
            'is_active'     => true,
        ]);

        $org = Organization::create([
            'name'                        => 'Jane Aesthetic Clinic',
            'slug'                        => 'jane-aesthetic',
            'type'                        => Organization::TYPE_PERSONAL,
            'status'                      => Organization::STATUS_ACTIVE,
            'owner_specialist_account_id' => $account->id,
        ]);

        $membership = OrganizationMembership::create([
            'organization_id'       => $org->id,
            'specialist_account_id' => $account->id,
            'role'                  => OrganizationMembership::ROLE_OWNER,
            'status'                => OrganizationMembership::STATUS_ACTIVE,
        ]);

        $this->assertTrue($membership->isSpecialist());
        $this->assertFalse($membership->isUser());
        $this->assertEquals($account->id, $membership->specialistAccount->id);
        $this->assertEquals($account->id, $membership->principal->id);
        $this->assertTrue($org->hasOwner($account));
        $this->assertTrue($org->hasAdmin($account));
        $this->assertTrue($org->hasMember($account));

        // SpecialistAccount relationships
        $this->assertCount(1, $account->organizationMemberships);
        $this->assertCount(1, $account->organizations);
        $this->assertCount(1, $account->ownedOrganizations);
        $this->assertEquals($org->id, $account->currentOrganization()->id);
    }

    public function test_user_organization_relationships(): void
    {
        $user = User::factory()->create();

        $org1 = Organization::create([
            'name'          => 'Brand One',
            'slug'          => 'brand-one',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org1->id,
            'user_id'         => $user->id,
            'role'            => OrganizationMembership::ROLE_OWNER,
        ]);

        $this->assertCount(1, $user->organizations);
        $this->assertCount(1, $user->ownedOrganizations);
        $this->assertEquals($org1->id, $user->organizations->first()->id);
    }

    public function test_provider_belongs_to_organization(): void
    {
        $user = User::factory()->create();

        $org = Organization::create([
            'name'          => 'Commercial Entity',
            'slug'          => 'commercial-entity',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        $type = ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true]
        );

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Downtown Salon',
            'slug'             => 'downtown-salon-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $this->assertEquals($org->id, $provider->organization->id);
        $this->assertCount(1, $org->providers);
    }

    public function test_subscription_belongs_to_organization(): void
    {
        $user = User::factory()->create();

        $org = Organization::create([
            'name'          => 'Billing Entity',
            'slug'          => 'billing-entity',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        $type = ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true]
        );

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'display_name'     => 'Westside Spa',
            'slug'             => 'westside-spa-' . uniqid(),
            'provider_type_id' => $type->id,
            'status'           => 'active',
            'active'           => true,
        ]);

        $plan = \App\Models\Plan::create([
            'name'          => 'Growth Plan',
            'slug'          => 'growth-plan-' . uniqid(),
            'monthly_price' => 49.00,
            'is_active'     => true,
        ]);

        $subscription = Subscription::create([
            'organization_id' => $org->id,
            'provider_id'     => $provider->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'starts_at'       => now(),
        ]);

        $this->assertEquals($org->id, $subscription->organization->id);
        $this->assertCount(1, $org->subscriptions);
    }

    public function test_db_check_constraint_rejects_organization_with_no_designated_owner(): void
    {
        $this->expectException(QueryException::class);

        Organization::create([
            'name'                        => 'Ownerless Org',
            'slug'                        => 'ownerless-org',
            'type'                        => Organization::TYPE_BUSINESS,
            'status'                      => Organization::STATUS_ACTIVE,
            'owner_user_id'               => null,
            'owner_specialist_account_id' => null,
        ]);
    }

    public function test_db_check_constraint_rejects_membership_with_dual_principals(): void
    {
        $user = User::factory()->create();
        $specialist = Specialist::create([
            'name'   => 'Dr Dual',
            'email'  => 'dual@example.com',
            'phone'  => '0700888888',
            'active' => true,
        ]);
        $account = SpecialistAccount::create([
            'id'            => (string) Str::uuid(),
            'specialist_id' => $specialist->id,
            'email'         => $specialist->email,
            'password'      => Hash::make('password'),
            'is_active'     => true,
        ]);

        $org = Organization::create([
            'name'          => 'Dual Test Org',
            'slug'          => 'dual-test-org',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to set BOTH user_id and specialist_account_id violates chk_membership_principal
        OrganizationMembership::create([
            'organization_id'       => $org->id,
            'user_id'               => $user->id,
            'specialist_account_id' => $account->id,
            'role'                  => OrganizationMembership::ROLE_MEMBER,
        ]);
    }

    public function test_db_check_constraint_rejects_membership_with_no_principal(): void
    {
        $user = User::factory()->create();

        $org = Organization::create([
            'name'          => 'No Principal Org',
            'slug'          => 'no-principal-org',
            'type'          => Organization::TYPE_BUSINESS,
            'owner_user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to set NEITHER user_id nor specialist_account_id violates chk_membership_principal
        OrganizationMembership::create([
            'organization_id'       => $org->id,
            'user_id'               => null,
            'specialist_account_id' => null,
            'role'                  => OrganizationMembership::ROLE_MEMBER,
        ]);
    }
}
