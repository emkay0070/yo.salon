<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Provider;
use App\Models\Salon;
use App\Models\SpecialistAssignment;
use App\Models\CustomerSpecialist;
use App\Models\Booking;
use App\Models\CustomerWorkspaceInvitation;
use App\Services\CustomerSpecialistService;
use App\Services\CustomerWorkspaceInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerWorkspaceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSpecialistService $customerSpecialistService;
    private CustomerWorkspaceInvitationService $invitationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerSpecialistService = new CustomerSpecialistService();
        $this->invitationService = new CustomerWorkspaceInvitationService();
    }

    /**
     * Test that salon specialist can only access customers from their salon workspace
     */
    public function test_salon_specialist_access_limited_to_salon_customers(): void
    {
        // Setup: Create salon, specialist, and customer
        $salonProvider = Provider::factory()->create(['type' => 'salon']);
        $salon = Salon::factory()->create(['provider_id' => $salonProvider->id]);
        
        $specialist = Specialist::factory()->create();
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'salon_id' => $salon->id,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create();

        // Create salon-specific relationship
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
        ]);

        // Create independent workspace for specialist
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Test: Specialist should only see salon customers when querying salon workspace
        $salonRelationships = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('provider_id', $salonProvider->id)
            ->get();

        $this->assertCount(1, $salonRelationships);
        $this->assertEquals($customer->id, $salonRelationships->first()->customer_id);

        // Independent workspace should have no customers initially
        $independentRelationships = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->get();

        $this->assertCount(0, $independentRelationships);
    }

    /**
     * Test that independent workspace starts empty
     */
    public function test_independent_workspace_starts_empty(): void
    {
        $specialist = Specialist::factory()->create();
        
        // Create independent workspace
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Verify no relationships exist in independent workspace
        $relationships = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->get();

        $this->assertCount(0, $relationships);
    }

    /**
     * Test that independent bookings create independent relationships
     */
    public function test_independent_bookings_create_independent_relationships(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Create booking in independent workspace
        $booking = Booking::factory()->create([
            'specialist_id' => $specialist->id,
            'customer_id' => $customer->id,
            'provider_id' => $independentProvider->id,
            'status' => 'completed',
        ]);

        // Track relationship from booking
        $relationship = $this->customerSpecialistService->trackRelationshipFromBooking($booking);

        // Verify relationship was created with correct provider context
        $this->assertEquals($customer->id, $relationship->customer_id);
        $this->assertEquals($specialist->id, $relationship->specialist_id);
        $this->assertEquals($independentProvider->id, $relationship->provider_id);
        $this->assertEquals(CustomerSpecialist::ORIGIN_INDEPENDENT, $relationship->relationship_origin);
        $this->assertEquals(CustomerSpecialist::SOURCE_BOOKING, $relationship->acquisition_source);
    }

    /**
     * Test multiple simultaneous relationships for same customer-specialist with different providers
     */
    public function test_multiple_simultaneous_relationships_different_providers(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $salonProvider = Provider::factory()->create(['type' => 'salon']);
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);

        // Create salon relationship
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
            'total_bookings' => 5,
        ]);

        // Create independent relationship
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_INDEPENDENT,
            'total_bookings' => 2,
        ]);

        // Verify both relationships exist independently
        $allRelationships = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->get();

        $this->assertCount(2, $allRelationships);

        // Verify salon relationship
        $salonRelationship = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $salonProvider->id)
            ->first();

        $this->assertNotNull($salonRelationship);
        $this->assertEquals(5, $salonRelationship->total_bookings);

        // Verify independent relationship
        $independentRelationship = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->first();

        $this->assertNotNull($independentRelationship);
        $this->assertEquals(2, $independentRelationship->total_bookings);
    }

    /**
     * Test that leaving salon does not transfer customers
     */
    public function test_leaving_salon_does_not_transfer_customers(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $salonProvider = Provider::factory()->create(['type' => 'salon']);
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);

        // Create salon assignment
        $salonAssignment = SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'status' => 'active',
        ]);

        // Create salon relationship
        $salonRelationship = CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
            'total_bookings' => 5,
        ]);

        // Specialist leaves salon (assignment becomes inactive)
        $salonAssignment->update(['status' => 'inactive']);

        // Create independent workspace
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Verify salon relationship still exists and wasn't transferred
        $existingSalonRelationship = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $salonProvider->id)
            ->first();

        $this->assertNotNull($existingSalonRelationship);
        $this->assertEquals($salonRelationship->id, $existingSalonRelationship->id);
        $this->assertEquals(5, $existingSalonRelationship->total_bookings);

        // Verify independent workspace has no customers (no automatic transfer)
        $independentRelationships = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->get();

        $this->assertCount(0, $independentRelationships);
    }

    /**
     * Test that workspace switching changes customer data
     */
    public function test_workspace_switching_changes_customer_data(): void
    {
        $specialist = Specialist::factory()->create();
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $salonProvider = Provider::factory()->create(['type' => 'salon']);
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);

        // Create salon relationships
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer1->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
        ]);

        CustomerSpecialist::factory()->create([
            'customer_id' => $customer2->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_SALON,
        ]);

        // Create independent relationship for customer2 only
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer2->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'relationship_origin' => CustomerSpecialist::ORIGIN_INDEPENDENT,
        ]);

        // Query salon workspace
        $salonCustomers = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('provider_id', $salonProvider->id)
            ->get();

        $this->assertCount(2, $salonCustomers);

        // Query independent workspace
        $independentCustomers = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->get();

        $this->assertCount(1, $independentCustomers);
        $this->assertEquals($customer2->id, $independentCustomers->first()->customer_id);
    }

    /**
     * Test that invitation creates relationship only when accepted
     */
    public function test_invitation_creates_relationship_only_when_accepted(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Create invitation
        $invitation = $this->invitationService->createInvitation(
            $customer->id,
            $specialist->id,
            $independentProvider->id,
            null,
            CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT
        );

        // Verify invitation exists but no relationship
        $this->assertEquals(CustomerWorkspaceInvitation::STATUS_SENT, $invitation->status);
        
        $relationship = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $independentProvider->id)
            ->first();

        $this->assertNull($relationship);

        // Accept invitation
        $relationship = $this->invitationService->acceptInvitation($invitation->id);

        // Verify relationship now exists
        $this->assertNotNull($relationship);
        $this->assertEquals($customer->id, $relationship->customer_id);
        $this->assertEquals($specialist->id, $relationship->specialist_id);
        $this->assertEquals($independentProvider->id, $relationship->provider_id);
        $this->assertEquals(CustomerSpecialist::ORIGIN_INDEPENDENT, $relationship->relationship_origin);
        $this->assertEquals(CustomerSpecialist::SOURCE_INVITATION, $relationship->acquisition_source);
    }

    /**
     * Test that source_provider_id tracks relationship origin
     */
    public function test_source_provider_id_tracks_relationship_origin(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $salonProvider = Provider::factory()->create(['type' => 'salon']);
        $independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);

        // Create salon assignment
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $salonProvider->id,
            'status' => 'active',
        ]);

        // Create independent assignment
        SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
            'provider_id' => $independentProvider->id,
            'status' => 'active',
        ]);

        // Create invitation from salon context to independent workspace
        $invitation = $this->invitationService->createInvitation(
            $customer->id,
            $specialist->id,
            $independentProvider->id,
            $salonProvider->id, // Source provider
            CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT
        );

        // Accept invitation
        $relationship = $this->invitationService->acceptInvitation($invitation->id);

        // Verify source_provider_id is preserved
        $this->assertEquals($salonProvider->id, $relationship->source_provider_id);
        $this->assertEquals(CustomerSpecialist::ORIGIN_INDEPENDENT, $relationship->relationship_origin);
    }

    /**
     * Test that relationship queries respect provider filtering
     */
    public function test_relationship_queries_respect_provider_filtering(): void
    {
        $specialist = Specialist::factory()->create();
        $customer = Customer::factory()->create();
        
        $provider1 = Provider::factory()->create(['type' => 'salon']);
        $provider2 = Provider::factory()->create(['type' => 'salon']);

        // Create relationships with different providers
        CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider1->id,
            'total_bookings' => 3,
        ]);

        CustomerSpecialist::factory()->create([
            'customer_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider2->id,
            'total_bookings' => 7,
        ]);

        // Query with provider filter
        $provider1Relationships = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $provider1->id)
            ->get();

        $this->assertCount(1, $provider1Relationships);
        $this->assertEquals(3, $provider1Relationships->first()->total_bookings);

        // Query without provider filter (should return all)
        $allRelationships = CustomerSpecialist::where('customer_id', $customer->id)
            ->where('specialist_id', $specialist->id)
            ->get();

        $this->assertCount(2, $allRelationships);
    }
}
