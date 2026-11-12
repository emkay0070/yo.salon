<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Booking;
use App\Models\User;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerLocationFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location1;
    private Location $location2;
    private Customer $customer1;
    private Customer $customer2;
    private Customer $customer3;
    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create provider with 2 locations
        $this->provider = Provider::factory()->create();
        $this->location1 = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->location2 = Location::factory()->create(['provider_id' => $this->provider->id]);

        // Create customers with customer_providers relationship
        $this->customer1 = Customer::factory()->create();
        $this->customer2 = Customer::factory()->create();
        $this->customer3 = Customer::factory()->create();

        // Attach customers to provider with UUIDs for pivot table
        $this->provider->customers()->attach([
            $this->customer1->id => ['id' => (string) Str::uuid()],
            $this->customer2->id => ['id' => (string) Str::uuid()],
            $this->customer3->id => ['id' => (string) Str::uuid()],
        ]);

        // Create owner user
        $this->ownerUser = User::factory()->create();
        ProviderUser::factory()->create([
            'user_id' => $this->ownerUser->id,
            'provider_id' => $this->provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);
    }

    /**
     * Test 1: Provider-wide list includes all provider-associated customers
     */
    public function test_provider_wide_list_includes_all_customers(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'customers');
        $response->assertJsonPath('metadata.locationScope', 'all');
    }

    /**
     * Test 2: Specific location returns only customers with eligible bookings at that location
     */
    public function test_specific_location_filters_by_bookings(): void
    {
        // Create bookings: customer1 at location1, customer2 at location2
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location1->id,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location2->id,
            'customer_id' => $this->customer2->id,
            'status' => 'completed',
        ]);

        // Request customers for location1
        $response = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location1->id,
            ])
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'customers');
        $response->assertJsonPath('customers.0.id', $this->customer1->id);
    }

    /**
     * Test 3: Customer with bookings at two locations appears in both views
     */
    public function test_customer_with_multiple_locations_appears_in_both(): void
    {
        // Customer1 has bookings at both locations
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location1->id,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location2->id,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        // Request for location1
        $response1 = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location1->id,
            ])
            ->getJson('/api/v1/customers');

        $response1->assertJsonCount(1, 'customers');
        $response1->assertJsonPath('customers.0.id', $this->customer1->id);

        // Request for location2
        $response2 = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location2->id,
            ])
            ->getJson('/api/v1/customers');

        $response2->assertJsonCount(1, 'customers');
        $response2->assertJsonPath('customers.0.id', $this->customer1->id);
    }

    /**
     * Test 4: Customer with only NULL-location bookings appears in "Location not recorded"
     */
    public function test_customer_with_null_location_bookings_appears_in_unassigned(): void
    {
        // Customer1 has bookings with NULL location
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        // Request with location_filter=unassigned
        $response = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers?location_filter=unassigned');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'customers');
        $response->assertJsonPath('customers.0.id', $this->customer1->id);
        $response->assertJsonPath('metadata.locationFilter', 'unassigned');
    }

    /**
     * Test 5: Customer with both NULL and real-location bookings appears in both categories
     */
    public function test_customer_with_both_null_and_real_bookings_appears_in_both(): void
    {
        // Customer1 has bookings at location1 and NULL location
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location1->id,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        // Request for location1
        $response1 = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location1->id,
            ])
            ->getJson('/api/v1/customers');

        $response1->assertJsonCount(1, 'customers');

        // Request with location_filter=unassigned
        $response2 = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers?location_filter=unassigned');

        $response2->assertJsonCount(1, 'customers');
    }

    /**
     * Test 6: Customer without eligible booking history appears in provider-wide list only
     */
    public function test_customer_without_bookings_appears_in_provider_wide_only(): void
    {
        // Customer3 has no bookings
        // Request provider-wide
        $response1 = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers');

        $response1->assertJsonCount(3, 'customers');

        // Request for location1
        $response2 = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location1->id,
            ])
            ->getJson('/api/v1/customers');

        $response2->assertJsonCount(0, 'customers');

        // Request with location_filter=unassigned
        $response3 = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers?location_filter=unassigned');

        $response3->assertJsonCount(0, 'customers');
    }

    /**
     * Test 7: Cancelled bookings do not count for location membership
     */
    public function test_cancelled_bookings_do_not_count(): void
    {
        // Customer1 has cancelled booking at location1
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location1->id,
            'customer_id' => $this->customer1->id,
            'status' => 'cancelled',
        ]);

        // Request for location1
        $response = $this->actingAs($this->ownerUser)
            ->withHeaders([
                'X-Provider-Id' => $this->provider->id,
                'X-Location-Id' => $this->location1->id,
            ])
            ->getJson('/api/v1/customers');

        $response->assertJsonCount(0, 'customers');
    }

    /**
     * Test 8: Location context metadata is included in response
     */
    public function test_location_context_metadata_included(): void
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location1->id,
            'customer_id' => $this->customer1->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'customers' => [
                '*' => [
                    'id',
                    'name',
                    'locationContext' => [
                        'hasBookingsAtLocation',
                        'hasNullLocationBookings',
                        'bookingLocations',
                    ],
                ],
            ],
            'metadata' => [
                'total',
                'locationScope',
                'counts' => [
                    'providerWide',
                    'byLocation',
                    'unassigned',
                ],
            ],
        ]);
    }

    /**
     * Test 9: Cross-provider isolation remains intact
     */
    public function test_cross_provider_isolation(): void
    {
        // Create another provider
        $otherProvider = Provider::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $otherProvider->customers()->attach([
            $otherCustomer->id => ['id' => (string) Str::uuid()],
        ]);

        // Request customers for original provider
        $response = $this->actingAs($this->ownerUser)
            ->withHeaders(['X-Provider-Id' => $this->provider->id])
            ->getJson('/api/v1/customers');

        $response->assertJsonCount(3, 'customers');
        $response->assertJsonMissing(['id' => $otherCustomer->id]);
    }
}
