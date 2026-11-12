<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\CustomerWorkspaceInvitation;
use App\Models\User;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\ProviderUser;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\PortalAccount;
use App\Models\SpecialistAccount;
use App\Models\Staff;
use App\Services\CustomerWorkspaceInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $managerUser;
    protected User $staffUser;
    protected Provider $provider;
    protected Provider $otherProvider;
    protected Customer $customer;
    protected Specialist $specialist;
    protected SpecialistAssignment $specialistAssignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->managerUser = User::factory()->create();
        $this->staffUser = User::factory()->create();
        $this->provider = Provider::factory()->create();
        $this->otherProvider = Provider::factory()->create();
        $this->customer = Customer::factory()->create();

        \App\Models\Location::factory()->create([
            'provider_id' => $this->provider->id,
            'is_primary' => true,
        ]);
        \App\Models\Location::factory()->create([
            'provider_id' => $this->otherProvider->id,
            'is_primary' => true,
        ]);

        ProviderUser::factory()->create([
            'user_id' => $this->adminUser->id,
            'provider_id' => $this->provider->id,
            'role' => 'owner',
        ]);

        ProviderUser::factory()->create([
            'user_id' => $this->managerUser->id,
            'provider_id' => $this->provider->id,
            'role' => 'manager',
        ]);

        ProviderUser::factory()->create([
            'user_id' => $this->staffUser->id,
            'provider_id' => $this->provider->id,
            'role' => 'staff',
        ]);

        $this->specialist = Specialist::factory()->create();
        $this->specialistAssignment = SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->provider->location_id,
            'status' => 'active',
        ]);
    }

    // ── Invitation Model Tests ──────────────────────────────────────

    public function test_invitation_belongs_to_provider()
    {
        $invitation = Invitation::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $invitation->provider_id);
        $this->assertEquals($this->provider->id, $invitation->provider->id);
    }

    public function test_invitation_can_be_issued_with_token()
    {
        $issueData = Invitation::issue([
            'provider_id' => $this->provider->id,
            'email' => 'new@example.com',
            'role' => 'staff',
            'status' => 'pending',
        ]);

        $this->assertArrayHasKey('invitation', $issueData);
        $this->assertArrayHasKey('rawToken', $issueData);
        $this->assertNotNull($issueData['rawToken']);
        $this->assertEquals(64, strlen($issueData['rawToken']));
        $this->assertEquals(
            hash('sha256', $issueData['rawToken']),
            $issueData['invitation']->token
        );
    }

    public function test_invitation_can_be_found_by_token()
    {
        $issueData = Invitation::issue([
            'provider_id' => $this->provider->id,
            'email' => 'findme@example.com',
            'role' => 'customer',
            'status' => 'pending',
        ]);

        $found = Invitation::findByToken($issueData['rawToken']);

        $this->assertNotNull($found);
        $this->assertEquals($issueData['invitation']->id, $found->id);
    }

    public function test_invitation_find_by_invalid_token_returns_null()
    {
        $this->assertNull(Invitation::findByToken('invalid-token-123'));
        $this->assertNull(Invitation::findByToken(''));
    }

    public function test_invitation_pending_scope()
    {
        Invitation::factory()->count(3)->pending()->create([
            'provider_id' => $this->provider->id,
        ]);
        Invitation::factory()->count(2)->expired()->create([
            'provider_id' => $this->provider->id,
        ]);
        Invitation::factory()->count(1)->accepted()->create([
            'provider_id' => $this->provider->id,
        ]);

        $pending = Invitation::pending()->get();

        $this->assertCount(3, $pending);
    }

    public function test_invitation_staff_scope()
    {
        Invitation::factory()->count(2)->staff()->create([
            'provider_id' => $this->provider->id,
        ]);
        Invitation::factory()->count(3)->customer()->create([
            'provider_id' => $this->provider->id,
        ]);

        $staffInvites = Invitation::staff()->get();

        $this->assertCount(2, $staffInvites);
        $staffInvites->each(fn ($i) => $this->assertEquals('staff', $i->role));
    }

    public function test_invitation_customer_scope()
    {
        Invitation::factory()->count(2)->staff()->create([
            'provider_id' => $this->provider->id,
        ]);
        Invitation::factory()->count(3)->customer()->create([
            'provider_id' => $this->provider->id,
        ]);

        $customerInvites = Invitation::customer()->get();

        $this->assertCount(3, $customerInvites);
        $customerInvites->each(fn ($i) => $this->assertEquals('customer', $i->role));
    }

    public function test_invitation_is_pending_check()
    {
        $invitation = Invitation::factory()->pending()->create();

        $this->assertTrue($invitation->isPending());
        $this->assertTrue($invitation->isValid());
    }

    public function test_invitation_is_accepted_check()
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->assertTrue($invitation->isAccepted());
        $this->assertFalse($invitation->isPending());
    }

    public function test_invitation_is_expired_check()
    {
        $invitation = Invitation::factory()->expired()->create();

        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isPending());
        $this->assertFalse($invitation->isValid());
    }

    public function test_invitation_public_join_url_for_customer()
    {
        $invitation = Invitation::factory()->customer()->create();
        $rawToken = Str::random(64);

        $url = $invitation->publicJoinUrl($rawToken);

        $this->assertStringContainsString('/portal/invite/', $url);
        $this->assertStringContainsString($rawToken, $url);
    }

    public function test_invitation_public_join_url_for_specialist()
    {
        $invitation = Invitation::factory()->specialist()->create();
        $rawToken = Str::random(64);

        $url = $invitation->publicJoinUrl($rawToken);

        $this->assertStringContainsString('/specialist-portal/invite/', $url);
        $this->assertStringContainsString($rawToken, $url);
    }

    public function test_invitation_public_join_url_for_staff()
    {
        $invitation = Invitation::factory()->staff()->create();
        $rawToken = Str::random(64);

        $url = $invitation->publicJoinUrl($rawToken);

        $this->assertStringContainsString('/invite/', $url);
        $this->assertStringNotContainsString('/portal/', $url);
        $this->assertStringNotContainsString('/specialist-portal/', $url);
    }

    // ── Invitation Factory States Tests ────────────────────────────

    public function test_factory_pending_state()
    {
        $invitation = Invitation::factory()->pending()->create();

        $this->assertEquals('pending', $invitation->status);
        $this->assertNull($invitation->accepted_at);
    }

    public function test_factory_accepted_state()
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->assertEquals('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_factory_expired_state()
    {
        $invitation = Invitation::factory()->expired()->create();

        $this->assertEquals('expired', $invitation->status);
    }

    public function test_factory_staff_role()
    {
        $invitation = Invitation::factory()->staff()->create();
        $this->assertEquals('staff', $invitation->role);
    }

    public function test_factory_customer_role()
    {
        $invitation = Invitation::factory()->customer()->create();
        $this->assertEquals('customer', $invitation->role);
    }

    public function test_factory_specialist_role()
    {
        $invitation = Invitation::factory()->specialist()->create();
        $this->assertEquals('specialist', $invitation->role);
    }

    public function test_factory_manager_role()
    {
        $invitation = Invitation::factory()->manager()->create();
        $this->assertEquals('manager', $invitation->role);
    }

    public function test_factory_receptionist_role()
    {
        $invitation = Invitation::factory()->receptionist()->create();
        $this->assertEquals('receptionist', $invitation->role);
    }

    public function test_factory_with_target()
    {
        $invitation = Invitation::factory()->withTarget()->create();
        $this->assertNotNull($invitation->target_id);
    }

    public function test_factory_without_expiration()
    {
        $invitation = Invitation::factory()->withoutExpiration()->create();
        $this->assertNull($invitation->expires_at);
    }

    // ── Invitation Provider Isolation Tests ────────────────────────

    public function test_provider_isolation_in_invitations_query()
    {
        Invitation::factory()->count(5)->create(['provider_id' => $this->provider->id]);
        Invitation::factory()->count(3)->create(['provider_id' => $this->otherProvider->id]);

        $providerInvitations = Invitation::where('provider_id', $this->provider->id)->get();

        $this->assertCount(5, $providerInvitations);
        $providerInvitations->each(fn ($i) => $this->assertEquals($this->provider->id, $i->provider_id));
    }

    // ── CustomerWorkspaceInvitation Model Tests ────────────────────

    public function test_cw_invitation_belongs_to_customer()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->assertEquals($this->customer->id, $invitation->customer_id);
        $this->assertEquals($this->customer->id, $invitation->customer->id);
    }

    public function test_cw_invitation_belongs_to_specialist()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->create([
            'specialist_id' => $this->specialist->id,
        ]);

        $this->assertEquals($this->specialist->id, $invitation->specialist_id);
        $this->assertEquals($this->specialist->id, $invitation->specialist->id);
    }

    public function test_cw_invitation_belongs_to_target_provider()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->create([
            'target_provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $invitation->target_provider_id);
        $this->assertEquals($this->provider->id, $invitation->targetProvider->id);
    }

    public function test_cw_invitation_belongs_to_source_provider()
    {
        $invitation = CustomerWorkspaceInvitation::factory()
            ->withSourceProvider($this->otherProvider)
            ->create();

        $this->assertEquals($this->otherProvider->id, $invitation->source_provider_id);
        $this->assertEquals($this->otherProvider->id, $invitation->sourceProvider->id);
    }

    public function test_cw_invitation_is_expired_check()
    {
        $expired = CustomerWorkspaceInvitation::factory()->expired()->create();
        $notExpired = CustomerWorkspaceInvitation::factory()->withExpiration(48)->create();

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($notExpired->isExpired());
    }

    public function test_cw_invitation_can_be_accepted_check()
    {
        $sent = CustomerWorkspaceInvitation::factory()->sent()->create();
        $opened = CustomerWorkspaceInvitation::factory()->opened()->create();
        $accepted = CustomerWorkspaceInvitation::factory()->accepted()->create();
        $expired = CustomerWorkspaceInvitation::factory()->expired()->create();

        $this->assertTrue($sent->canBeAccepted());
        $this->assertTrue($opened->canBeAccepted());
        $this->assertFalse($accepted->canBeAccepted());
        $this->assertFalse($expired->canBeAccepted());
    }

    public function test_cw_invitation_mark_as_opened()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();

        $invitation->markAsOpened();

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_OPENED, $invitation->status);
        $this->assertNotNull($invitation->opened_at);
    }

    public function test_cw_invitation_mark_as_accepted()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->opened()->create();

        $invitation->markAsAccepted();

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_cw_invitation_mark_as_declined()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();

        $invitation->markAsDeclined();

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_DECLINED, $invitation->status);
        $this->assertNotNull($invitation->declined_at);
    }

    public function test_cw_invitation_mark_as_expired()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();

        $invitation->markAsExpired();

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_EXPIRED, $invitation->status);
    }

    public function test_cw_invitation_pending_scope()
    {
        CustomerWorkspaceInvitation::factory()->count(3)->pending()->create();
        CustomerWorkspaceInvitation::factory()->count(2)->accepted()->create();
        CustomerWorkspaceInvitation::factory()->count(1)->declined()->create();

        $pending = CustomerWorkspaceInvitation::pending()->get();

        $this->assertCount(3, $pending);
    }

    public function test_cw_invitation_to_provider_scope()
    {
        CustomerWorkspaceInvitation::factory()->count(2)->create([
            'target_provider_id' => $this->provider->id,
        ]);
        CustomerWorkspaceInvitation::factory()->count(3)->create([
            'target_provider_id' => $this->otherProvider->id,
        ]);

        $toProvider = CustomerWorkspaceInvitation::toProvider($this->provider->id)->get();

        $this->assertCount(2, $toProvider);
    }

    public function test_cw_invitation_from_specialist_scope()
    {
        $otherSpecialist = Specialist::factory()->create();

        CustomerWorkspaceInvitation::factory()->count(2)->create([
            'specialist_id' => $this->specialist->id,
        ]);
        CustomerWorkspaceInvitation::factory()->count(3)->create([
            'specialist_id' => $otherSpecialist->id,
        ]);

        $fromSpecialist = CustomerWorkspaceInvitation::fromSpecialist($this->specialist->id)->get();

        $this->assertCount(2, $fromSpecialist);
    }

    // ── CustomerWorkspaceInvitation Factory Tests ──────────────────

    public function test_cw_factory_sent_state()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_SENT, $invitation->status);
    }

    public function test_cw_factory_opened_state()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->opened()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_OPENED, $invitation->status);
    }

    public function test_cw_factory_accepted_state()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->accepted()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_ACCEPTED, $invitation->status);
    }

    public function test_cw_factory_declined_state()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->declined()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_DECLINED, $invitation->status);
    }

    public function test_cw_factory_expired_state()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->expired()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_EXPIRED, $invitation->status);
    }

    public function test_cw_factory_specialist_direct_channel()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->specialistDirect()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT, $invitation->channel);
    }

    public function test_cw_factory_platform_suggestion_channel()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->platformSuggestion()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::CHANNEL_PLATFORM_SUGGESTION, $invitation->channel);
    }

    public function test_cw_factory_referral_channel()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->referral()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::CHANNEL_REFERRAL, $invitation->channel);
    }

    public function test_cw_factory_direct_link_channel()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->directLink()->create();
        $this->assertEquals(CustomerWorkspaceInvitation::CHANNEL_DIRECT_LINK, $invitation->channel);
    }

    public function test_cw_factory_with_expiration()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->withExpiration(24)->create();
        $this->assertNotNull($invitation->expires_at);
    }

    public function test_cw_factory_without_expiration()
    {
        $invitation = CustomerWorkspaceInvitation::factory()->withoutExpiration()->create();
        $this->assertNull($invitation->expires_at);
    }

    // ── CustomerWorkspaceInvitationService Tests ───────────────────

    public function test_cw_service_create_invitation_validates_specialist_assignment()
    {
        $service = app(CustomerWorkspaceInvitationService::class);
        $unassignedSpecialist = Specialist::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $service->createInvitation(
            customerId: $this->customer->id,
            specialistId: $unassignedSpecialist->id,
            targetProviderId: $this->provider->id,
        );
    }

    public function test_cw_service_create_invitation_returns_existing_pending()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        $first = $service->createInvitation(
            customerId: $this->customer->id,
            specialistId: $this->specialist->id,
            targetProviderId: $this->provider->id,
        );

        $second = $service->createInvitation(
            customerId: $this->customer->id,
            specialistId: $this->specialist->id,
            targetProviderId: $this->provider->id,
        );

        $this->assertEquals($first->id, $second->id);
    }

    public function test_cw_service_mark_as_opened()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();
        $result = $service->markAsOpened($invitation->id);

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_OPENED, $result->status);
    }

    public function test_cw_service_accept_invitation_creates_relationship()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'target_provider_id' => $this->provider->id,
        ]);

        $relationship = $service->acceptInvitation($invitation->id);

        $this->assertNotNull($relationship);
        $this->assertEquals($this->customer->id, $relationship->customer_id);
        $this->assertEquals($this->specialist->id, $relationship->specialist_id);
        $this->assertEquals($this->provider->id, $relationship->provider_id);

        $invitation->refresh();
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_ACCEPTED, $invitation->status);
    }

    public function test_cw_service_decline_invitation()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create();
        $result = $service->declineInvitation($invitation->id);

        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_DECLINED, $result->status);
    }

    public function test_cw_service_get_pending_invitations_for_customer()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        CustomerWorkspaceInvitation::factory()->count(2)->pending()->create([
            'customer_id' => $this->customer->id,
        ]);
        CustomerWorkspaceInvitation::factory()->count(1)->accepted()->create([
            'customer_id' => $this->customer->id,
        ]);

        $pending = $service->getPendingInvitationsForCustomer($this->customer->id);

        $this->assertCount(2, $pending);
    }

    public function test_cw_service_get_invitations_by_specialist()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        CustomerWorkspaceInvitation::factory()->count(3)->create([
            'specialist_id' => $this->specialist->id,
            'target_provider_id' => $this->provider->id,
        ]);

        $invitations = $service->getInvitationsBySpecialist($this->specialist->id, $this->provider->id);

        $this->assertCount(3, $invitations);
    }

    public function test_cw_service_get_invitation_stats()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        CustomerWorkspaceInvitation::factory()->count(2)->sent()->create([
            'specialist_id' => $this->specialist->id,
            'target_provider_id' => $this->provider->id,
        ]);
        CustomerWorkspaceInvitation::factory()->count(1)->accepted()->create([
            'specialist_id' => $this->specialist->id,
            'target_provider_id' => $this->provider->id,
        ]);
        CustomerWorkspaceInvitation::factory()->count(1)->declined()->create([
            'specialist_id' => $this->specialist->id,
            'target_provider_id' => $this->provider->id,
        ]);

        $stats = $service->getInvitationStats($this->specialist->id, $this->provider->id);

        $this->assertEquals(4, $stats['total_sent']);
        $this->assertEquals(1, $stats['accepted']);
        $this->assertEquals(1, $stats['declined']);
        $this->assertEquals(2, $stats['pending']);
    }

    public function test_cw_service_expire_old_invitations()
    {
        $service = app(CustomerWorkspaceInvitationService::class);

        CustomerWorkspaceInvitation::factory()->count(2)->create([
            'status' => CustomerWorkspaceInvitation::STATUS_SENT,
            'expires_at' => now()->subDays(5),
        ]);
        CustomerWorkspaceInvitation::factory()->count(1)->withExpiration(48)->create();

        $count = $service->expireOldInvitations();

        $this->assertEquals(2, $count);
    }

    // ── Invitation Controller Public Flow Tests ────────────────────

    public function test_invitation_show_validates_token()
    {
        $issueData = Invitation::issue([
            'provider_id' => $this->provider->id,
            'email' => 'show@example.com',
            'role' => 'customer',
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/invitations/{$issueData['rawToken']}");

        $response->assertStatus(200)
            ->assertJsonStructure(['provider_name', 'role', 'email']);
    }

    public function test_invitation_show_rejects_invalid_token()
    {
        $response = $this->getJson('/api/v1/invitations/invalid-token-123');

        $response->assertStatus(404);
    }

    public function test_invitation_accept_customer_new_customer()
    {
        $issueData = Invitation::issue([
            'provider_id' => $this->provider->id,
            'email' => 'newcustomer@example.com',
            'role' => 'customer',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/invitations/{$issueData['rawToken']}/accept", [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'portal_account']);

        $this->assertTrue(PortalAccount::where('email', 'newcustomer@example.com')->exists());
    }

    public function test_invitation_accept_staff_new_user()
    {
        $issueData = Invitation::issue([
            'provider_id' => $this->provider->id,
            'email' => 'newstaff@example.com',
            'role' => 'staff',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/invitations/{$issueData['rawToken']}/accept", [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'staff']);

        $this->assertTrue(User::where('email', 'newstaff@example.com')->exists());
        $this->assertTrue(Staff::where('email', 'newstaff@example.com')->exists());
    }

    public function test_invitation_accept_rejects_expired_invitation()
    {
        $invitation = Invitation::factory()->expired()->create([
            'provider_id' => $this->provider->id,
            'email' => 'expired@example.com',
            'role' => 'customer',
        ]);

        $rawToken = 'expired-token';
        $invitation->update(['token' => hash('sha256', $rawToken)]);

        $response = $this->postJson("/api/v1/invitations/{$rawToken}/accept", [
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(400);
    }

    // ── Invitation Policy Tests ────────────────────────────────────

    public function test_admin_can_view_any_invitations()
    {
        $this->actingAs($this->adminUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertTrue($policy->viewAny($this->adminUser));
    }

    public function test_manager_can_view_any_invitations()
    {
        $this->actingAs($this->managerUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertTrue($policy->viewAny($this->managerUser));
    }

    public function test_staff_cannot_view_any_invitations()
    {
        $this->actingAs($this->staffUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertFalse($policy->viewAny($this->staffUser));
    }

    public function test_cross_provider_access_rejected_for_view()
    {
        $this->actingAs($this->adminUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $otherInvitation = Invitation::factory()->create([
            'provider_id' => $this->otherProvider->id,
        ]);

        $this->assertFalse($policy->view($this->adminUser, $otherInvitation));
    }

    public function test_admin_can_create_invitations()
    {
        $this->actingAs($this->adminUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertTrue($policy->create($this->adminUser));
    }

    public function test_manager_can_create_invitations()
    {
        $this->actingAs($this->managerUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertTrue($policy->create($this->managerUser));
    }

    public function test_staff_cannot_create_invitations()
    {
        $this->actingAs($this->staffUser);
        $policy = app(\App\Policies\InvitationPolicy::class);

        $this->assertFalse($policy->create($this->staffUser));
    }

    // ── CustomerWorkspaceInvitation Policy Tests ───────────────────

    public function test_cw_specialist_can_view_own_invitations()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);

        $invitation = CustomerWorkspaceInvitation::factory()->create([
            'specialist_id' => $this->specialist->id,
        ]);

        $this->assertTrue($policy->view($this->specialist, $invitation));
    }

    public function test_cw_specialist_cannot_view_other_specialist_invitations()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);
        $otherSpecialist = Specialist::factory()->create();

        $invitation = CustomerWorkspaceInvitation::factory()->create([
            'specialist_id' => $this->specialist->id,
        ]);

        $this->assertFalse($policy->view($otherSpecialist, $invitation));
    }

    public function test_cw_specialist_with_assignment_can_create_invitation()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);

        $this->assertTrue($policy->create($this->specialist, $this->provider->id));
    }

    public function test_cw_specialist_without_assignment_cannot_create_invitation()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);
        $unassigned = Specialist::factory()->create();

        $this->assertFalse($policy->create($unassigned, $this->provider->id));
    }

    public function test_cw_customer_can_accept_own_invitation()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);

        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->assertTrue($policy->accept($this->customer, $invitation));
    }

    public function test_cw_customer_cannot_accept_other_customer_invitation()
    {
        $policy = app(\App\Policies\CustomerWorkspaceInvitationPolicy::class);
        $otherCustomer = Customer::factory()->create();

        $invitation = CustomerWorkspaceInvitation::factory()->sent()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->assertFalse($policy->accept($otherCustomer, $invitation));
    }
}
