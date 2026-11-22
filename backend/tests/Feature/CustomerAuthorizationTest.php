<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\PortalAccount;
use App\Models\ProfessionalAssessment;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\SpecialistAssignment;
use App\Models\SpecialistNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CustomerAuthorizationTest
 *
 * Verifies multi-tenant customer isolation, route model authorization,
 * cross-provider protection, and data integrity safeguards.
 *
 * FIND-CRIT-03 remediation suite.
 */
class CustomerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Provider $providerA;
    private Provider $providerB;
    private Location $locationA;
    private Location $locationB;
    private User $userA;
    private User $userB;
    private Customer $customerA;
    private Customer $customerB;
    private Service $serviceA;

    protected function setUp(): void
    {
        parent::setUp();

        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        // Setup Provider A
        $this->providerA = Provider::factory()->create(['name' => 'Salon Alpha', 'active' => true]);
        $this->locationA = Location::factory()->create(['provider_id' => $this->providerA->id]);
        $this->userA = User::factory()->create(['status' => 'active', 'email' => 'staff.a@example.com']);
        ProviderUser::factory()->owner()->create([
            'user_id' => $this->userA->id,
            'provider_id' => $this->providerA->id,
        ]);

        // Setup Provider B
        $this->providerB = Provider::factory()->create(['name' => 'Salon Beta', 'active' => true]);
        $this->locationB = Location::factory()->create(['provider_id' => $this->providerB->id]);
        $this->userB = User::factory()->create(['status' => 'active', 'email' => 'staff.b@example.com']);
        ProviderUser::factory()->owner()->create([
            'user_id' => $this->userB->id,
            'provider_id' => $this->providerB->id,
        ]);

        // Create Customer A associated exclusively with Provider A
        $this->customerA = Customer::factory()->create([
            'name' => 'Alice Customer',
            'phone' => '+15550000001',
            'email' => 'alice@example.com',
        ]);
        $this->providerA->customers()->attach([
            $this->customerA->id => ['id' => (string) Str::uuid()],
        ]);

        // Create Customer B associated exclusively with Provider B
        $this->customerB = Customer::factory()->create([
            'name' => 'Bob Customer',
            'phone' => '+15550000002',
            'email' => 'bob@example.com',
        ]);
        $this->providerB->customers()->attach([
            $this->customerB->id => ['id' => (string) Str::uuid()],
        ]);

        // Create a service for Provider A
        $this->serviceA = Service::factory()->create([
            'provider_id' => $this->providerA->id,
            'name' => 'Haircut',
            'duration' => 30,
            'price' => 50000,
        ]);
    }

    // =========================================================================
    // Customer Management (Scenarios 1-5)
    // =========================================================================

    /**
     * 1. Provider A can view its own customer.
     */
    public function test_provider_a_can_view_own_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->customerA->id,
            'name' => 'Alice Customer',
        ]);
    }

    /**
     * 2. Provider A cannot view Provider B's customer.
     */
    public function test_provider_a_cannot_view_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('GET', "/api/v1/customers/{$this->customerB->id}");

        $response->assertStatus(403);
    }

    /**
     * 3. Provider A cannot update Provider B's customer.
     */
    public function test_provider_a_cannot_update_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('PUT', "/api/v1/customers/{$this->customerB->id}", [
            'name' => 'Tampered Bob',
            'email' => 'attacker@example.com',
        ]);

        $response->assertStatus(403);

        $this->customerB->refresh();
        $this->assertEquals('Bob Customer', $this->customerB->name);
        $this->assertEquals('bob@example.com', $this->customerB->email);
    }

    /**
     * 4. Provider A cannot delete Provider B's customer.
     */
    public function test_provider_a_cannot_delete_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('DELETE', "/api/v1/customers/{$this->customerB->id}");

        $response->assertStatus(403);

        // Global record must remain intact
        $this->assertDatabaseHas('customers', ['id' => $this->customerB->id]);
        // Pivot relationship to Provider B must remain intact
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $this->customerB->id,
            'provider_id' => $this->providerB->id,
        ]);
    }

    /**
     * 5. Denied requests leave customer data unchanged.
     */
    public function test_denied_requests_leave_customer_data_unchanged(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $this->json('PATCH', "/api/v1/customers/{$this->customerB->id}", [
            'name' => 'Malicious Change',
            'phone' => '+19999999999',
        ])->assertStatus(403);

        $fresh = Customer::findOrFail($this->customerB->id);
        $this->assertEquals('Bob Customer', $fresh->name);
        $this->assertEquals('+15550000002', $fresh->phone);
    }

    // =========================================================================
    // Assessments and Notes (Scenarios 6-9)
    // =========================================================================

    /**
     * 6. Provider A cannot retrieve Provider B's customer profile.
     */
    public function test_provider_a_cannot_retrieve_provider_b_customer_profile(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('GET', "/api/v1/customers/{$this->customerB->id}/profile");

        // Privacy-preserving 404 response
        $response->assertStatus(404);
        $this->assertNull($response->json('customer'));
        $this->assertNull($response->json('grooming_profile'));
    }

    /**
     * 7. Provider A cannot create an assessment for Provider B's customer.
     */
    public function test_provider_a_cannot_create_assessment_for_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $specialistA = Specialist::factory()->create(['name' => 'Specialist Alpha']);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialistA->id,
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->json('POST', '/api/v1/assessments', [
            'customer_id' => $this->customerB->id,
            'specialist_id' => $specialistA->id,
            'salon_id' => $this->locationA->id,
            'observed_hair_type' => 'Straight',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 8. Provider A cannot create a note for Provider B's customer.
     */
    public function test_provider_a_cannot_create_note_for_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $specialistA = Specialist::factory()->create(['name' => 'Specialist Alpha']);

        $response = $this->json('POST', '/api/v1/notes', [
            'customer_id' => $this->customerB->id,
            'specialist_id' => $specialistA->id,
            'note' => 'Confidential note from Provider A',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 9. Denied requests create no assessment or note records.
     */
    public function test_denied_requests_create_no_assessment_or_note_records(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $specialistA = Specialist::factory()->create(['name' => 'Specialist Alpha']);

        $this->json('POST', '/api/v1/assessments', [
            'customer_id' => $this->customerB->id,
            'specialist_id' => $specialistA->id,
            'salon_id' => $this->locationA->id,
            'observed_hair_type' => 'Curly',
        ])->assertStatus(403);

        $this->json('POST', '/api/v1/notes', [
            'customer_id' => $this->customerB->id,
            'specialist_id' => $specialistA->id,
            'note' => 'Unauthorized note attempt',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('professional_assessments', [
            'customer_id' => $this->customerB->id,
        ]);
        $this->assertDatabaseMissing('specialist_notes', [
            'customer_id' => $this->customerB->id,
        ]);
    }

    // =========================================================================
    // Portal Invitations (Scenarios 10-11)
    // =========================================================================

    /**
     * 10. Provider A cannot invite Provider B's customer.
     */
    public function test_provider_a_cannot_invite_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/portal/send-invitation', [
            'customer_id' => $this->customerB->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * 11. Denied requests dispatch no invitation.
     */
    public function test_denied_invitation_dispatches_no_invitation(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $this->json('POST', '/api/v1/portal/send-invitation', [
            'customer_id' => $this->customerB->id,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('invitations', [
            'target_id' => $this->customerB->id,
            'provider_id' => $this->providerA->id,
        ]);
    }

    // =========================================================================
    // Bookings (Scenarios 12-15)
    // =========================================================================

    /**
     * 12. Provider A cannot arbitrarily attach Provider B's customer via booking.
     */
    public function test_provider_a_cannot_arbitrarily_attach_provider_b_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerB->id,
            'service_id' => [$this->serviceA->id],
            'date' => now()->addDays(2)->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(403);

        // Ensure no pivot attachment occurred
        $this->assertDatabaseMissing('customer_providers', [
            'customer_id' => $this->customerB->id,
            'provider_id' => $this->providerA->id,
        ]);
    }

    /**
     * 13. Provider A can book for an existing Provider A customer.
     */
    public function test_provider_a_can_book_for_existing_provider_a_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerA->id,
            'service_id' => [$this->serviceA->id],
            'date' => now()->addDays(2)->toDateString(),
            'time' => '11:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'provider_id' => $this->providerA->id,
            'customer_id' => $this->customerA->id,
        ]);
    }

    /**
     * 14. New customer booking still works.
     */
    public function test_new_customer_booking_still_works(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_name' => 'Charlie Walkin',
            'customer_phone' => '+15550000003',
            'customer_email' => 'charlie@example.com',
            'service_id' => [$this->serviceA->id],
            'date' => now()->addDays(3)->toDateString(),
            'time' => '14:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['phone' => '+15550000003']);
        $newCustomer = Customer::where('phone', '+15550000003')->first();
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $newCustomer->id,
            'provider_id' => $this->providerA->id,
        ]);
    }

    /**
     * 15. Legitimate multi-Provider customer behavior remains supported where explicitly intended.
     */
    public function test_legitimate_multi_provider_customer_behavior_is_supported(): void
    {
        // Attach Customer A legitimately to Provider B as well (e.g. customer visits both salons)
        $this->providerB->customers()->attach([
            $this->customerA->id => ['id' => (string) Str::uuid()],
        ]);

        // Provider A can view Customer A
        Sanctum::actingAs($this->userA, ['*']);
        $responseA = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");
        $responseA->assertStatus(200);

        // Provider B can also view Customer A
        Sanctum::actingAs($this->userB, ['*']);
        $responseB = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");
        $responseB->assertStatus(200);

        // Provider A removes Customer A from Provider A
        Sanctum::actingAs($this->userA, ['*']);
        $deleteResponse = $this->json('DELETE', "/api/v1/customers/{$this->customerA->id}");
        $deleteResponse->assertStatus(200);

        // Provider A's pivot is gone
        $this->assertDatabaseMissing('customer_providers', [
            'customer_id' => $this->customerA->id,
            'provider_id' => $this->providerA->id,
        ]);

        // Global Customer record still exists!
        $this->assertDatabaseHas('customers', ['id' => $this->customerA->id]);

        // Provider B's pivot still exists!
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $this->customerA->id,
            'provider_id' => $this->providerB->id,
        ]);

        // Provider B can STILL view Customer A
        Sanctum::actingAs($this->userB, ['*']);
        $this->json('GET', "/api/v1/customers/{$this->customerA->id}")->assertStatus(200);

        // Provider A now gets 403
        Sanctum::actingAs($this->userA, ['*']);
        $this->json('GET', "/api/v1/customers/{$this->customerA->id}")->assertStatus(403);
    }

    // =========================================================================
    // Context and Identity (Scenarios 16-20)
    // =========================================================================

    /**
     * 16. Missing Provider context is denied.
     */
    public function test_missing_provider_context_is_denied(): void
    {
        // Unauthenticated request
        $response = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");
        $response->assertStatus(401);
    }

    /**
     * 17. Invalid or unauthorized Provider context is denied.
     */
    public function test_invalid_or_unauthorized_provider_context_is_denied(): void
    {
        // User A belongs to Provider A. Attempt to spoof Provider B via payload
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerB->id,
            'location_id' => $this->locationB->id,
            'customer_id' => $this->customerB->id,
            'service_id' => [$this->serviceA->id],
            'date' => now()->addDays(2)->toDateString(),
            'time' => '10:00',
        ]);

        // Rejected because provider_id does not match User A's active account
        $response->assertStatus(403);
    }

    /**
     * 18. User without Provider membership cannot access customer records.
     */
    public function test_user_without_provider_membership_cannot_access_customer_records(): void
    {
        $unassociatedUser = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($unassociatedUser, ['*']);

        $response = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");
        $response->assertStatus(403);
    }

    /**
     * 19. SpecialistAccount behavior follows its actual authorized Provider relationship.
     */
    public function test_specialist_account_behavior_follows_authorized_provider_relationship(): void
    {
        $specialist = Specialist::factory()->create();
        $specialistAccount = SpecialistAccount::create([
            'id' => (string) Str::uuid(),
            'specialist_id' => $specialist->id,
            'email' => 'specialist@example.com',
            'password' => bcrypt('password'),
        ]);

        // Specialist is NOT assigned to Provider B
        Sanctum::actingAs($specialistAccount, ['*']);

        $response = $this->json('GET', "/api/v1/customers/{$this->customerB->id}/profile");
        // Denied / 404 because specialist has no relationship with Customer B or Provider B
        $response->assertStatus(404);
    }

    /**
     * 20. PortalAccount cannot gain staff customer-management privileges.
     */
    public function test_portal_account_cannot_gain_staff_customer_management_privileges(): void
    {
        $portalAccount = PortalAccount::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $this->customerA->id,
            'email' => 'portal.alice@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($portalAccount, ['*']);

        // Portal customer cannot call staff customer endpoints
        $responseShow = $this->json('GET', "/api/v1/customers/{$this->customerA->id}");
        $responseShow->assertStatus(403);

        $responseUpdate = $this->json('PUT', "/api/v1/customers/{$this->customerA->id}", [
            'name' => 'Should Not Work',
        ]);
        $responseUpdate->assertStatus(403);

        $responseDestroy = $this->json('DELETE', "/api/v1/customers/{$this->customerA->id}");
        $responseDestroy->assertStatus(403);
    }

    // =========================================================================
    // FIND-CRIT-04 / R-1: Customer Store & Cross-Provider Disclosure Safeguards
    // =========================================================================

    /**
     * 21. Provider A receives existing customer when phone belongs to an already-associated customer.
     */
    public function test_store_returns_existing_customer_when_associated_with_active_provider(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/customers', [
            'name' => 'Alice Updated Name',
            'phone' => '+15550000001', // Customer A's phone
            'email' => 'alice@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Customer already exists',
            'customer' => [
                'id' => $this->customerA->id,
                'name' => 'Alice Customer',
                'phone' => '+15550000001',
            ],
        ]);
    }

    /**
     * 22. Matching phone belonging exclusively to Provider B is NEVER disclosed to Provider A,
     * and Provider B's customer is NOT attached to Provider A.
     */
    public function test_store_never_discloses_or_attaches_another_providers_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        // Provider A attempts to store a customer with Provider B's customer's phone
        $response = $this->json('POST', '/api/v1/customers', [
            'name' => 'Intruder Query',
            'phone' => '+15550000002', // Customer B's phone
            'email' => 'intruder@example.com',
        ]);

        // Must NOT return 200 with Customer B's details
        $response->assertStatus(201);
        $response->assertJsonMissing(['name' => 'Bob Customer']);
        $response->assertJsonMissing(['email' => 'bob@example.com']);

        // Customer B must NOT be attached to Provider A
        $this->assertFalse(
            $this->providerA->customers()->where('customers.id', $this->customerB->id)->exists()
        );

        // Customer B's data must remain unmodified
        $this->assertDatabaseHas('customers', [
            'id' => $this->customerB->id,
            'name' => 'Bob Customer',
            'email' => 'bob@example.com',
            'phone' => '+15550000002',
        ]);

        // A new, distinct customer was created for Provider A
        $newCustomerId = $response->json('id');
        $this->assertNotEmpty($newCustomerId);
        $this->assertNotEquals($this->customerB->id, $newCustomerId);
        $this->assertTrue(
            $this->providerA->customers()->where('customers.id', $newCustomerId)->exists()
        );
    }

    /**
     * 23. Legitimate multi-provider customer associated with both providers is resolved for each.
     */
    public function test_store_preserves_legitimate_multi_provider_customers(): void
    {
        $sharedCustomer = Customer::factory()->create([
            'name' => 'Shared Customer',
            'phone' => '+15550000099',
            'email' => 'shared@example.com',
        ]);
        $this->providerA->customers()->attach($sharedCustomer->id, ['id' => (string) Str::uuid()]);
        $this->providerB->customers()->attach($sharedCustomer->id, ['id' => (string) Str::uuid()]);

        // Provider A queries/stores with shared phone -> gets 200 with shared customer
        Sanctum::actingAs($this->userA, ['*']);
        $responseA = $this->json('POST', '/api/v1/customers', [
            'name' => 'Shared Customer',
            'phone' => '+15550000099',
        ]);
        $responseA->assertStatus(200);
        $responseA->assertJson([
            'message' => 'Customer already exists',
            'customer' => [
                'id' => $sharedCustomer->id,
            ],
        ]);

        // Provider B queries/stores with shared phone -> gets 200 with shared customer
        Sanctum::actingAs($this->userB, ['*']);
        $responseB = $this->json('POST', '/api/v1/customers', [
            'name' => 'Shared Customer',
            'phone' => '+15550000099',
        ]);
        $responseB->assertStatus(200);
        $responseB->assertJson([
            'message' => 'Customer already exists',
            'customer' => [
                'id' => $sharedCustomer->id,
            ],
        ]);
    }

    /**
     * 24. New customer creation without existing collisions associates correctly with active Provider.
     */
    public function test_store_creates_and_associates_brand_new_customer(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->json('POST', '/api/v1/customers', [
            'name' => 'David New',
            'phone' => '+15559998888',
            'email' => 'david@example.com',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'name' => 'David New',
            'phone' => '+15559998888',
            'email' => 'david@example.com',
        ]);

        $newId = $response->json('id');
        $this->assertTrue(
            $this->providerA->customers()->where('customers.id', $newId)->exists()
        );
        $this->assertFalse(
            $this->providerB->customers()->where('customers.id', $newId)->exists()
        );
    }

    // =========================================================================
    // FIND-CRIT-04 / R-2: BookingService Cross-Provider Customer Safeguards
    // =========================================================================

    /**
     * 25. Public booking with another Provider's existing phone:
     * - Does NOT disclose another Provider's customer details.
     * - Does NOT silently attach another Provider's customer.
     * - Does NOT permit portal account takeover on the existing customer.
     */
    public function test_public_booking_with_cross_provider_phone_prevents_disclosure_attachment_and_takeover(): void
    {
        $response = $this->json('POST', '/api/v1/bookings/with-account', [
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_name' => 'Imposter Attacker',
            'customer_phone' => '+15550000002', // Customer B's phone
            'customer_email' => 'imposter@example.com',
            'create_account' => true,
            'account_email' => 'takeover@example.com',
            'account_password' => 'TakeoverPass123!',
            'service_id' => [$this->serviceA->id],
            'date' => now()->addDays(2)->format('Y-m-d'),
            'time' => '11:00',
        ]);

        $response->assertStatus(201);

        // 1. No cross-Provider customer disclosure
        $response->assertJsonMissing(['name' => 'Bob Customer']);
        $response->assertJsonMissing(['email' => 'bob@example.com']);

        // 2. No unauthorized customer-provider pivot attachment
        $this->assertFalse(
            $this->providerA->customers()->where('customers.id', $this->customerB->id)->exists()
        );

        // 3. No portal account takeover on victim
        $this->assertNull(
            PortalAccount::where('customer_id', $this->customerB->id)->first()
        );

        // 4. Victim data remains intact in database
        $this->assertDatabaseHas('customers', [
            'id' => $this->customerB->id,
            'name' => 'Bob Customer',
            'email' => 'bob@example.com',
            'phone' => '+15550000002',
        ]);

        // 5. Booking was assigned to a new, distinct customer for Provider A
        $bookedCustomerId = $response->json('booking.customer_id');
        $this->assertNotEquals($this->customerB->id, $bookedCustomerId);
        $this->assertTrue(
            $this->providerA->customers()->where('customers.id', $bookedCustomerId)->exists()
        );
    }

    /**
     * 26. Existing same-Provider customer booking correctly resolves existing record.
     */
    public function test_booking_with_same_provider_existing_phone_resolves_existing_customer(): void
    {
        $bookingService = app(\App\Services\BookingService::class);

        $booking = $bookingService->createGuestBooking([
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_name' => 'Alice Repeat',
            'customer_phone' => '+15550000001', // Customer A's phone
            'customer_email' => 'alice@example.com',
            'service_id' => $this->serviceA->id,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'time' => '12:00',
            'internal_booking' => true,
        ]);

        // Resolves the existing Customer A
        $this->assertEquals($this->customerA->id, $booking->customer_id);

        // Does not create duplicate customer_providers pivot row
        $pivotCount = DB::table('customer_providers')
            ->where('customer_id', $this->customerA->id)
            ->where('provider_id', $this->providerA->id)
            ->count();
        $this->assertEquals(1, $pivotCount);
    }

    /**
     * 27. Legitimate multi-Provider customer booking preserves both associations.
     */
    public function test_booking_preserves_legitimate_multi_provider_customer_associations(): void
    {
        $multiCustomer = Customer::factory()->create([
            'name' => 'Multi Patron',
            'phone' => '+15550000077',
            'email' => 'multi@example.com',
        ]);
        $this->providerA->customers()->attach($multiCustomer->id, ['id' => (string) Str::uuid()]);
        $this->providerB->customers()->attach($multiCustomer->id, ['id' => (string) Str::uuid()]);

        $bookingService = app(\App\Services\BookingService::class);

        // Booking at Provider A resolves multiCustomer
        $bookingA = $bookingService->createGuestBooking([
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_phone' => '+15550000077',
            'customer_name' => 'Multi Patron',
            'service_id' => $this->serviceA->id,
            'date' => now()->addDays(4)->format('Y-m-d'),
            'time' => '13:00',
            'internal_booking' => true,
        ]);
        $this->assertEquals($multiCustomer->id, $bookingA->customer_id);

        // Booking at Provider B resolves multiCustomer
        $serviceB = Service::factory()->create([
            'provider_id' => $this->providerB->id,
            'name' => 'Service B',
            'duration' => 30,
            'price' => 40000,
        ]);
        $bookingB = $bookingService->createGuestBooking([
            'provider_id' => $this->providerB->id,
            'location_id' => $this->locationB->id,
            'customer_phone' => '+15550000077',
            'customer_name' => 'Multi Patron',
            'service_id' => $serviceB->id,
            'date' => now()->addDays(4)->format('Y-m-d'),
            'time' => '15:00',
            'internal_booking' => true,
        ]);
        $this->assertEquals($multiCustomer->id, $bookingB->customer_id);

        // MultiCustomer still has exactly 2 provider relationships
        $this->assertEquals(2, $multiCustomer->providers()->count());
    }

    /**
     * 28. Existing-customer rescheduling via rebookFromPrevious retains existing customer identity.
     */
    public function test_rebooking_from_previous_preserves_customer_identity(): void
    {
        $bookingService = app(\App\Services\BookingService::class);

        $initialBooking = $bookingService->createGuestBooking([
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerA->id,
            'service_id' => $this->serviceA->id,
            'date' => now()->addDays(1)->format('Y-m-d'),
            'time' => '10:00',
            'internal_booking' => true,
        ]);

        $rescheduledBooking = $bookingService->rebookFromPrevious($initialBooking->id, [
            'date' => now()->addDays(5)->format('Y-m-d'),
            'time' => '16:00',
            'location_id' => $this->locationA->id,
        ]);

        $this->assertEquals($this->customerA->id, $rescheduledBooking->customer_id);
        $this->assertEquals($this->providerA->id, $rescheduledBooking->provider_id);
    }

    /**
     * 29. Authenticated staff booking isolates providers and prevents spoofing.
     */
    public function test_staff_booking_enforces_provider_isolation(): void
    {
        Sanctum::actingAs($this->userA, ['*']);

        // Staff A attempts to create booking under Provider B
        $responseSpoof = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerB->id,
            'location_id' => $this->locationB->id,
            'customer_name' => 'Spoof Attempt',
            'customer_phone' => '+15559990001',
            'service_id' => $this->serviceA->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'time' => '10:00',
        ]);
        $responseSpoof->assertStatus(403);

        // Staff A creates booking under Provider A with Provider B's phone
        $responseLegit = $this->json('POST', '/api/v1/bookings', [
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'customer_name' => 'Walkin Client',
            'customer_phone' => '+15550000002', // Customer B's phone
            'service_id' => $this->serviceA->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'time' => '10:00',
            'internal_booking' => true,
        ]);
        $responseLegit->assertStatus(201);

        // Customer B is not attached to Provider A
        $this->assertFalse(
            $this->providerA->customers()->where('customers.id', $this->customerB->id)->exists()
        );
    }

    // =========================================================================
    // R-3: PortalAuthService::register() — Provider-scoped customer resolution
    // =========================================================================

    /**
     * Scenario 30: A customer already associated with Provider A can register
     * a portal account successfully.
     */
    public function test_portal_registration_succeeds_for_customer_associated_with_provider(): void
    {
        // customerA is already associated with providerA (set up in setUp())
        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => $this->customerA->phone,
            'email'       => 'newaccount@example.com',
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);
        $result  = $service->register($data);

        $this->assertArrayHasKey('portal_account', $result);
        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals($this->customerA->id, $result['customer']->id);
        $this->assertFalse($result['is_new_customer']);
        $this->assertFalse($result['is_new_provider_relationship']);

        \App\Models\PortalAccount::where('customer_id', $this->customerA->id)->delete();
    }

    /**
     * Scenario 31: A phone that belongs exclusively to Provider B must not
     * allow registration under Provider A. No PortalAccount must be created
     * for the victim customer.
     */
    public function test_portal_registration_fails_when_phone_belongs_only_to_other_provider(): void
    {
        // customerB belongs exclusively to providerB
        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => $this->customerB->phone,
            'email'       => 'attacker@example.com',
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->register($data);

        // No PortalAccount must have been created for victim customerB
        $this->assertDatabaseMissing('portal_accounts', [
            'customer_id' => $this->customerB->id,
        ]);
    }

    /**
     * Scenario 32: An email that belongs exclusively to a Provider B customer
     * must not allow registration under Provider A.
     */
    public function test_portal_registration_fails_when_email_belongs_only_to_other_provider(): void
    {
        // Use a non-matching phone so email fallback is exercised
        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => '+15550009999', // unknown phone
            'email'       => $this->customerB->email,
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->register($data);

        $this->assertDatabaseMissing('portal_accounts', [
            'customer_id' => $this->customerB->id,
        ]);
    }

    /**
     * Scenario 33: A rejected cross-Provider registration must NOT create a
     * customer_providers pivot row between the victim and the requesting Provider.
     */
    public function test_portal_registration_rejection_creates_no_cross_provider_pivot_row(): void
    {
        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => $this->customerB->phone,
            'email'       => 'attacker@example.com',
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);

        try {
            $service->register($data);
        } catch (\Illuminate\Validation\ValidationException) {
            // Expected
        }

        // customerB must NOT have been attached to providerA
        $this->assertFalse(
            $this->providerA->customers()->where('customers.id', $this->customerB->id)->exists(),
            'Cross-Provider pivot row must not be created on rejected registration.'
        );
    }

    /**
     * Scenario 34: A customer who already has a portal account must receive a
     * validation error rather than creating a duplicate account.
     */
    public function test_portal_registration_fails_when_customer_already_has_portal_account(): void
    {
        // Create a portal account for customerA
        $existing = \App\Models\PortalAccount::create([
            'customer_id' => $this->customerA->id,
            'email'       => 'existing@example.com',
            'password'    => \Illuminate\Support\Facades\Hash::make('Secret123!'),
        ]);

        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => $this->customerA->phone,
            'email'       => 'duplicate@example.com',
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->register($data);

        $existing->delete();
    }

    /**
     * Scenario 35: A successful registration returns the expected response
     * structure with is_new_provider_relationship always false.
     */
    public function test_portal_registration_response_structure_is_correct(): void
    {
        $data = [
            'provider_id' => $this->providerA->id,
            'phone'       => $this->customerA->phone,
            'email'       => 'structure-check@example.com',
            'password'    => 'Secret123!',
        ];

        $service = app(\App\Services\PortalAuthService::class);
        $result  = $service->register($data);

        $this->assertArrayHasKey('portal_account', $result);
        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('location', $result);
        $this->assertArrayHasKey('is_new_customer', $result);
        $this->assertArrayHasKey('is_new_provider_relationship', $result);
        $this->assertFalse($result['is_new_customer']);
        // R-3: Provider-scoped lookup guarantees the relationship pre-exists; never true.
        $this->assertFalse($result['is_new_provider_relationship']);

        \App\Models\PortalAccount::where('customer_id', $this->customerA->id)->delete();
    }

    // =========================================================================
    // R-5: InvitationController — Phone pollution fix ('TBD' -> null)
    // =========================================================================

    /**
     * Scenario 36: Accepting a customer invitation when no customer record exists
     * creates a Customer with phone = null, never the literal string 'TBD'.
     */
    public function test_invitation_acceptance_sets_phone_to_null_never_tbd(): void
    {
        $invitation = \App\Models\Invitation::create([
            'provider_id' => $this->providerA->id,
            'location_id' => $this->locationA->id,
            'email'       => 'invited.customer@example.com',
            'role'        => 'customer',
            'token'       => \Illuminate\Support\Str::random(32),
            'status'      => 'pending',
            'expires_at'  => now()->addDays(7),
        ]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'name'     => 'Invited Customer',
            'email'    => 'invited.customer@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(200);

        // Verify Customer was created with phone = null
        $createdCustomer = Customer::where('email', 'invited.customer@example.com')->first();
        $this->assertNotNull($createdCustomer);
        $this->assertNull($createdCustomer->phone, 'Customer phone must be null, never "TBD".');

        // Confirm database does not contain phone = 'TBD'
        $this->assertDatabaseMissing('customers', [
            'phone' => 'TBD',
        ]);
    }

    // =========================================================================
    // R-7: Phone Normalization Integration Tests
    // =========================================================================

    /**
     * Scenario 37: Customer model setPhoneAttribute normalizes inputs to E.164
     * and sets empty / null values to null.
     */
    public function test_customer_model_normalizes_phone_on_save(): void
    {
        $customer = new Customer();
        $customer->name = 'Format Test';

        // Equivalent formatted phone should normalize to E.164
        $customer->phone = '+1 (555) 234-5678';
        $this->assertEquals('+15552345678', $customer->phone);

        // International 00 prefix
        $customer->phone = '0044 20 7946 0958';
        $this->assertEquals('+442079460958', $customer->phone);

        // Empty string becomes null
        $customer->phone = '';
        $this->assertNull($customer->phone);

        // TBD placeholder becomes null
        $customer->phone = 'TBD';
        $this->assertNull($customer->phone);
    }

    /**
     * Scenario 38: Customer::findByContact() with formatted number matches
     * canonical E.164 customer phone in database.
     */
    public function test_find_by_contact_resolves_differently_formatted_phone(): void
    {
        // customerA has phone '+15550000001'
        $found = Customer::findByContact('+1 (555) 000-0001');
        $this->assertNotNull($found);
        $this->assertEquals($this->customerA->id, $found->id);

        $foundAlt = Customer::findByContact('0015550000001');
        $this->assertNotNull($foundAlt);
        $this->assertEquals($this->customerA->id, $foundAlt->id);
    }
}
