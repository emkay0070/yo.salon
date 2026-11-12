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
}
