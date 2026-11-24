<?php

namespace Tests\Feature;

use App\Domain\Platform\PlatformRole;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PortalAccount;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEndpointAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );
    }

    /**
     * 1. Unauthenticated requests must receive 401 Unauthenticated.
     */
    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $endpoints = [
            ['GET', '/api/v1/admin/seeders'],
            ['POST', '/api/v1/admin/seeders/TestSeeder/run'],
            ['POST', '/api/v1/admin/seeders/batch-run'],
            ['GET', '/api/v1/admin/platform-payments/pending-invoices'],
            ['POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/verify'],
            ['POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/reject'],
            ['GET', '/api/v1/admin/schedule-migration/providers'],
            ['POST', '/api/v1/admin/schedule-migration/notify'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * 2. Authenticated ordinary User without platform role receives 403 Forbidden.
     */
    public function test_authenticated_ordinary_user_is_forbidden_with_403(): void
    {
        $user = User::factory()->create([
            'email' => 'regular.user@example.com',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $endpoints = [
            ['GET', '/api/v1/admin/seeders'],
            ['POST', '/api/v1/admin/seeders/TestSeeder/run'],
            ['POST', '/api/v1/admin/seeders/batch-run'],
            ['GET', '/api/v1/admin/platform-payments/pending-invoices'],
            ['POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/verify'],
            ['POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/reject'],
            ['GET', '/api/v1/admin/schedule-migration/providers'],
            ['POST', '/api/v1/admin/schedule-migration/notify'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(403);
            $response->assertJsonStructure(['message']);
        }
    }

    /**
     * 3. Organization Owner without platform privileges receives 403 Forbidden.
     */
    public function test_organization_owner_without_platform_role_is_forbidden(): void
    {
        $owner = User::factory()->create([
            'email' => 'org.owner@business.com',
            'status' => 'active',
        ]);

        $org = Organization::create([
            'name' => 'Commercial Org',
            'slug' => 'commercial-org-' . Str::random(6),
            'type' => Organization::TYPE_BUSINESS,
            'status' => Organization::STATUS_ACTIVE,
            'owner_user_id' => $owner->id,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMembership::ROLE_OWNER,
            'status' => OrganizationMembership::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($owner, ['*']);

        // Must NOT gain access to platform administration
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/seeders/TestSeeder/run')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/verify')->assertStatus(403);
    }

    /**
     * 4. Provider staff member is forbidden from platform administration.
     */
    public function test_provider_staff_member_is_forbidden(): void
    {
        $staffUser = User::factory()->create([
            'email' => 'staff.member@salon.com',
            'status' => 'active',
        ]);

        $organization = Organization::create([
            'name' => 'Test Salon Org',
            'slug' => 'test-salon-org-' . Str::random(6),
            'type' => Organization::TYPE_BUSINESS,
            'status' => Organization::STATUS_ACTIVE,
            'owner_user_id' => $staffUser->id,
        ]);

        $type = ProviderType::where('key', 'salon')->first();
        $provider = Provider::create([
            'organization_id' => $organization->id,
            'display_name' => 'Test Salon',
            'slug' => 'test-salon-' . Str::random(6),
            'provider_type_id' => $type->id,
            'status' => 'active',
        ]);

        ProviderUser::factory()->create([
            'user_id' => $staffUser->id,
            'provider_id' => $provider->id,
            'role' => 'employee',
            'authority_scope' => 'location_scoped',
        ]);

        Sanctum::actingAs($staffUser, ['*']);

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(403);
    }

    /**
     * 5. SpecialistAccount is strictly forbidden from platform administrative operations.
     */
    public function test_specialist_account_is_forbidden(): void
    {
        $specialist = Specialist::create([
            'name' => 'Emma Hair Stylist',
            'email' => 'emma.stylist@example.com',
            'active' => true,
        ]);

        $specialistAccount = SpecialistAccount::create([
            'id' => Str::uuid(),
            'specialist_id' => $specialist->id,
            'email' => 'emma.stylist@example.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($specialistAccount, ['*']);

        $response = $this->json('GET', '/api/v1/admin/seeders');
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized: Specialist and customer portal accounts are forbidden from platform administration.',
        ]);

        $response = $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices');
        $response->assertStatus(403);
    }

    /**
     * 6. PortalAccount is strictly forbidden from platform administrative operations.
     */
    public function test_portal_account_is_forbidden(): void
    {
        $customer = Customer::create([
            'name' => 'John Customer',
            'email' => 'john.customer@example.com',
            'phone' => '+256700111222',
        ]);

        $portalAccount = PortalAccount::create([
            'id' => Str::uuid(),
            'customer_id' => $customer->id,
            'email' => 'john.customer@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        Sanctum::actingAs($portalAccount, ['*']);

        $response = $this->json('GET', '/api/v1/admin/seeders');
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized: Specialist and customer portal accounts are forbidden from platform administration.',
        ]);

        $response = $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices');
        $response->assertStatus(403);
    }

    /**
     * 7. Authorized Platform Owner has full access to administrative endpoints in non-production.
     */
    public function test_authorized_platform_owner_has_full_access(): void
    {
        $owner = User::factory()->create([
            'email' => 'platform.owner@tybuel.io',
            'status' => 'active',
        ]);
        $owner->platform_role = PlatformRole::PLATFORM_OWNER;

        Sanctum::actingAs($owner, ['*']);

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/schedule-migration/providers')->assertStatus(200);
    }

    /**
     * 8. Authorized Platform Super Admin has full administrative access.
     */
    public function test_authorized_platform_super_admin_access(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'super.admin@tybuel.io',
            'status' => 'active',
        ]);
        $superAdmin->platform_role = PlatformRole::PLATFORM_SUPER_ADMIN;

        Sanctum::actingAs($superAdmin, ['*']);

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods')->assertStatus(200);
    }

    /**
     * 9. Developer role can view seeders and invoices, but CANNOT execute seeders or verify invoices.
     */
    public function test_developer_role_access_boundaries(): void
    {
        $developer = User::factory()->create([
            'email' => 'dev.lead@tybuel.io',
            'status' => 'active',
        ]);
        $developer->platform_role = PlatformRole::DEVELOPER;

        Sanctum::actingAs($developer, ['*']);

        // Developer can view seeders and invoices
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods')->assertStatus(200);

        // Developer CANNOT run seeders (seeder_execution is restricted to Owner & Super Admin)
        $this->json('POST', '/api/v1/admin/seeders/TestSeeder/run')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/seeders/batch-run', ['seeder_classes' => ['Test']])->assertStatus(403);

        // Developer CANNOT verify or reject invoices (invoices_verify_reject is restricted to Owner & Super Admin)
        $this->json('POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/verify')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/reject', ['reason' => 'test'])->assertStatus(403);

        // Developer CANNOT view decrypted payment method details (credentials)
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods/' . Str::uuid() . '/details')->assertStatus(403);
    }

    /**
     * 10. Support role can view invoices, but has ZERO access to seeders, verification, or method secrets.
     */
    public function test_support_role_access_boundaries(): void
    {
        $support = User::factory()->create([
            'email' => 'support.agent@tybuel.io',
            'status' => 'active',
        ]);
        $support->platform_role = PlatformRole::SUPPORT;

        Sanctum::actingAs($support, ['*']);

        // Support CAN view pending invoices and active methods to help customers
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods')->assertStatus(200);

        // Support CANNOT access seeder operations (seeder_view and seeder_execution are blocked)
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/seeders/TestSeeder/run')->assertStatus(403);

        // Support CANNOT approve or reject subscription payments
        $this->json('POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/verify')->assertStatus(403);
        $this->json('POST', '/api/v1/admin/platform-payments/invoices/' . Str::uuid() . '/reject', ['reason' => 'test'])->assertStatus(403);

        // Support CANNOT view decrypted gateway credentials
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods/' . Str::uuid() . '/details')->assertStatus(403);
    }

    /**
     * 11. Production environment strictly blocks seeder execution even for Platform Owner.
     */
    public function test_production_environment_blocks_seeder_execution_even_for_platform_owner(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $owner = User::factory()->create([
            'email' => 'platform.owner@tybuel.io',
            'status' => 'active',
        ]);
        $owner->platform_role = PlatformRole::PLATFORM_OWNER;

        Sanctum::actingAs($owner, ['*']);

        $response = $this->json('POST', '/api/v1/admin/seeders/TestSeeder/run');
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Database seeding is disabled in production environments.',
        ]);
    }

    /**
     * 12. Email Security: An unverified account matching a configured platform email receives no platform privileges.
     */
    public function test_unverified_account_matching_configured_platform_email_receives_no_privileges(): void
    {
        config(['platform.roles.platform_owner' => ['owner.unverified@tybuel.io']]);

        $user = User::factory()->unverified()->create([
            'email' => 'owner.unverified@tybuel.io',
            'status' => 'registered',
        ]);

        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->email_verified_at);

        Sanctum::actingAs($user, ['*']);

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/schedule-migration/providers')->assertStatus(403);
    }

    /**
     * 13. Email Security: A verified account matching a configured platform email receives its intended role.
     */
    public function test_verified_configured_platform_email_receives_intended_role(): void
    {
        config(['platform.roles.platform_owner' => ['owner.verified@tybuel.io']]);

        $user = User::factory()->create([
            'email' => 'owner.verified@tybuel.io',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->assertTrue($user->hasVerifiedEmail());

        Sanctum::actingAs($user, ['*']);

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/schedule-migration/providers')->assertStatus(200);
    }

    /**
     * 14. Email Security: Changing email via PATCH /api/v1/me invalidates verification and prevents privilege escalation.
     */
    public function test_user_changing_email_invalidates_previous_verification_and_privileges(): void
    {
        config(['platform.roles.platform_owner' => ['target.owner@tybuel.io']]);

        // Ordinary user with verified email
        $user = User::factory()->create([
            'email' => 'initial.user@example.com',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->assertTrue($user->hasVerifiedEmail());

        Sanctum::actingAs($user, ['*']);

        // User attempts to change email to configured platform administrator
        $response = $this->patchJson('/api/v1/me', [
            'email' => 'target.owner@tybuel.io',
        ]);
        $response->assertStatus(200);

        $user->refresh();

        $this->assertEquals('target.owner@tybuel.io', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());

        // Must NOT receive platform privileges because email is unverified
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(403);

        // Once legitimately verified, platform privileges become active
        $user->email_verified_at = now();
        $user->save();

        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
    }

    /**
     * 15. Email Security: Model lifecycle hook invalidates verification on direct email update.
     */
    public function test_model_saving_hook_invalidates_verification_on_direct_email_update(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
            'email_verified_at' => now(),
            'status' => 'email_verified',
        ]);

        $this->assertTrue($user->hasVerifiedEmail());

        $user->email = 'updated@example.com';
        $user->save();

        $user->refresh();

        $this->assertEquals('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('registered', $user->status);
    }

    /**
     * 16. Route Isolation: Platform administrator without a Provider association can access administrative endpoints.
     */
    public function test_platform_administrator_without_provider_association_can_access_endpoints(): void
    {
        $admin = User::factory()->create([
            'email' => 'pure.admin@tybuel.io',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $admin->platform_role = PlatformRole::PLATFORM_OWNER;

        // Confirm user has NO provider association
        $this->assertEquals(0, $admin->providers()->count());
        $this->assertNull($admin->currentProvider());

        Sanctum::actingAs($admin, ['*']);

        // Must succeed because platform admin routes are decoupled from provider.context
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/platform-payments/payment-methods')->assertStatus(200);
        $this->json('GET', '/api/v1/admin/schedule-migration/providers')->assertStatus(200);
    }

    /**
     * 17. Route Isolation: Ordinary user without Provider receives 403 on admin, and tenant routes still enforce provider context.
     */
    public function test_ordinary_user_without_provider_receives_403_and_tenant_routes_still_enforce_provider_context(): void
    {
        $user = User::factory()->create([
            'email' => 'no.provider@example.com',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->assertEquals(0, $user->providers()->count());

        Sanctum::actingAs($user, ['*']);

        // Admin endpoints reject with 403 (insufficient platform role)
        $this->json('GET', '/api/v1/admin/seeders')->assertStatus(403);
        $this->json('GET', '/api/v1/admin/platform-payments/pending-invoices')->assertStatus(403);

        // Tenant operational routes reject with 403 ("User is not associated with a provider")
        $response = $this->json('GET', '/api/v1/pulse');
        $response->assertStatus(403);
        $response->assertJson(['message' => 'User is not associated with a provider']);
    }
}
