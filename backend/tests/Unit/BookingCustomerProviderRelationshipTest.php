<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Provider;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class BookingCustomerProviderRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerService = app(CustomerService::class);
    }

    public function test_add_provider_relationship_creates_provider_relationship()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        
        $this->customerService->addProviderRelationship($customer, $provider->id);
        
        // Verify customer_providers relationship exists
        $hasProviderRelationship = $customer->providers()->where('provider_id', $provider->id)->exists();
        $this->assertTrue($hasProviderRelationship);
    }

    public function test_add_provider_relationship_is_idempotent()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        
        // Call twice
        $this->customerService->addProviderRelationship($customer, $provider->id);
        $this->customerService->addProviderRelationship($customer, $provider->id);
        
        // Verify only one provider relationship exists
        $relationshipCount = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $provider->id)
            ->count();
        
        $this->assertEquals(1, $relationshipCount);
    }

    public function test_provider_a_customer_cannot_leak_to_provider_b()
    {
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $customer = Customer::factory()->create();
        
        $this->customerService->addProviderRelationship($customer, $providerA->id);
        
        // Verify customer has relationship with Provider A
        $hasProviderARelationship = $customer->providers()->where('provider_id', $providerA->id)->exists();
        $this->assertTrue($hasProviderARelationship);
        
        // Verify customer does NOT have relationship with Provider B
        $hasProviderBRelationship = $customer->providers()->where('provider_id', $providerB->id)->exists();
        $this->assertFalse($hasProviderBRelationship);
    }

    public function test_customer_can_have_multiple_provider_relationships()
    {
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $customer = Customer::factory()->create();
        
        $this->customerService->addProviderRelationship($customer, $providerA->id);
        $this->customerService->addProviderRelationship($customer, $providerB->id);
        
        // Verify customer has relationships with both providers
        $hasProviderARelationship = $customer->providers()->where('provider_id', $providerA->id)->exists();
        $hasProviderBRelationship = $customer->providers()->where('provider_id', $providerB->id)->exists();
        
        $this->assertTrue($hasProviderARelationship);
        $this->assertTrue($hasProviderBRelationship);
    }

    /**
     * R-4 regression: resolveOrCreateForBusiness() previously called the
     * non-existent CustomerResolver::resolveOrCreateForSalon() and would
     * throw a fatal BadMethodCallException at runtime. Verify it no longer
     * crashes and correctly delegates to resolveOrCreateForProvider().
     */
    public function test_resolve_or_create_for_business_does_not_throw_bad_method_call_exception(): void
    {
        $provider = Provider::factory()->create();

        $data = [
            'name'  => 'Test Customer',
            'phone' => '+15550001234',
            'email' => 'test-r4@example.com',
        ];

        // Must not throw BadMethodCallException / Error
        $result = $this->customerService->resolveOrCreateForBusiness($data, $provider->id, true);

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('is_new', $result);
        $this->assertArrayHasKey('is_new_salon_relationship', $result);
        $this->assertInstanceOf(\App\Models\Customer::class, $result['customer']);
        $this->assertTrue($result['is_new'], 'Customer should be newly created.');

        // The new customer must be associated with the Provider
        $this->assertTrue(
            $result['customer']->providers()->where('provider_id', $provider->id)->exists(),
            'resolveOrCreateForBusiness() must establish a customer_providers relationship.'
        );
    }

    /**
     * R-4 regression: resolveOrCreateForBusiness() with an existing customer
     * and createIfNotFound=false must return the existing customer, not throw.
     */
    public function test_resolve_or_create_for_business_returns_existing_customer_when_create_disabled(): void
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create(['phone' => '+15550005678']);
        $customer->providers()->attach($provider->id, [
            'id'        => (string) \Illuminate\Support\Str::uuid(),
            'visits'    => 0,
            'joined_at' => now(),
        ]);

        $data = [
            'name'  => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
        ];

        $result = $this->customerService->resolveOrCreateForBusiness($data, $provider->id, false);

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals($customer->id, $result['customer']->id);
        $this->assertFalse($result['is_new'], 'Existing customer must not be flagged as new.');
    }
}
