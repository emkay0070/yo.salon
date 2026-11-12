<?php

namespace Tests\Feature;

use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\SpecialistAssignment;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpecialistPortalCustomersTest extends TestCase
{
    use RefreshDatabase;

    private Specialist $specialist;
    private SpecialistAccount $account;
    private Provider $provider;
    private Location $location1;
    private Location $location2;
    private Customer $customer1;
    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create provider
        $this->provider = Provider::factory()->create([
            'slug' => 'test-provider',
            'type' => 'salon',
        ]);

        // Create locations
        $this->location1 = Location::factory()->create([
            'provider_id' => $this->provider->id,
            'slug' => 'location-1',
            'name' => 'Location 1',
        ]);

        $this->location2 = Location::factory()->create([
            'provider_id' => $this->provider->id,
            'slug' => 'location-2',
            'name' => 'Location 2',
        ]);

        // Create specialist
        $this->specialist = Specialist::factory()->create();
        $this->account = SpecialistAccount::create([
            'id' => Str::uuid(),
            'specialist_id' => $this->specialist->id,
            'email' => 'specialist@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create customers
        $this->customer1 = Customer::factory()->create(['name' => 'Customer 1']);
        $this->customer2 = Customer::factory()->create(['name' => 'Customer 2']);
    }

    public function test_provider_isolation(): void
    {
        // Create another provider
        $otherProvider = Provider::factory()->create(['slug' => 'other-provider']);
        
        // Create assignment to other provider
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $otherProvider->id,
            'status' => 'ACTIVE',
        ]);

        // Create customer relationship with test provider
        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
            'total_bookings' => 1,
        ]);

        // Create assignment to test provider
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null, // Provider-wide access
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Request with other provider slug should fail
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=other-provider"
        );
        $response->assertStatus(200);
        
        // Request with test provider slug should succeed
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider"
        );
        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Customer 1');
    }

    public function test_invalid_provider_slug(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=non-existent-provider"
        );

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Invalid workspace context - no active assignment');
    }

    public function test_invalid_location_slug(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null,
        ]);

        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=non-existent-location"
        );

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Location not found or does not belong to this provider');
    }

    public function test_forbidden_location(): void
    {
        // Assignment to location 1 only
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => $this->location1->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Try to access location 2
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=location-2"
        );

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You do not have access to this location');
    }

    public function test_provider_wide_assignment(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null, // Provider-wide
        ]);

        // Create customer relationships
        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);
        $this->specialist->customers()->attach($this->customer2, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Without location_slug, should return all provider customers
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider"
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'customers')
            ->assertJsonPath('provider_wide_access', true)
            ->assertJsonPath('location_filtered', false);
    }

    public function test_multiple_location_specific_assignments(): void
    {
        // Assign to both locations
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => $this->location1->id,
        ]);

        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => $this->location2->id,
        ]);

        // Create bookings for customers at different locations
        Booking::factory()->create([
            'customer_id' => $this->customer1->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location1->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'customer_id' => $this->customer2->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location2->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        // Create customer relationships
        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);
        $this->specialist->customers()->attach($this->customer2, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Without location_slug, should require location selection
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider"
        );

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Location selection required - you have location-specific access only')
            ->assertJsonCount(2, 'available_locations');

        // With location_slug=location-1, should return only customers with bookings at location 1
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=location-1"
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Customer 1')
            ->assertJsonPath('location_filtered', true);
    }

    public function test_mixed_provider_wide_and_specific_assignments(): void
    {
        // Provider-wide assignment
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null,
        ]);

        // Also location-specific assignment
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => $this->location1->id,
        ]);

        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Without location_slug, should return all (provider-wide access)
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider"
        );

        $response->assertStatus(200)
            ->assertJsonPath('provider_wide_access', true);

        // With location_slug, should filter to that location
        Booking::factory()->create([
            'customer_id' => $this->customer1->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location1->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=location-1"
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('location_filtered', true);
    }

    public function test_location_specific_booking_filtering(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => $this->location1->id,
        ]);

        // Customer 1 has booking at location 1
        Booking::factory()->create([
            'customer_id' => $this->customer1->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location1->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        // Customer 2 has booking at location 2 only
        Booking::factory()->create([
            'customer_id' => $this->customer2->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location2->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        // Create customer relationships
        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);
        $this->specialist->customers()->attach($this->customer2, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Filter by location 1 - should only see customer 1
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=location-1"
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Customer 1');
    }

    public function test_customers_without_portal_accounts(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null,
        ]);

        // Customer without portal account
        $customerWithoutAccount = Customer::factory()->create([
            'name' => 'Guest Customer',
            'phone' => null,
            'email' => null,
        ]);

        Booking::factory()->create([
            'customer_id' => $customerWithoutAccount->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location1->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        $this->specialist->customers()->attach($customerWithoutAccount, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider"
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Guest Customer');
    }

    public function test_provider_wide_access_to_specific_location(): void
    {
        // Provider-wide assignment
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
            'location_id' => null,
        ]);

        Booking::factory()->create([
            'customer_id' => $this->customer1->id,
            'specialist_id' => $this->specialist->id,
            'location_id' => $this->location1->id,
            'provider_id' => $this->provider->id,
            'status' => 'completed',
        ]);

        $this->specialist->customers()->attach($this->customer1, [
            'provider_id' => $this->provider->id,
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        // Provider-wide specialist should be able to access specific locations
        $response = $this->withToken($token)->getJson(
            "/api/v1/specialist-portal/customers?provider_slug=test-provider&location_slug=location-1"
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('location_filtered', true);
    }

    public function test_no_provider_slug_returns_error(): void
    {
        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'status' => 'ACTIVE',
        ]);

        $token = $this->account->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/specialist-portal/customers');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'provider_slug is required for workspace-specific customer access');
    }
}
