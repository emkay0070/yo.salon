<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\PortalAccount;
use App\Models\Service;
use App\Services\CustomerResolver;
use App\Services\PortalAuthService;
use App\Services\CustomerBookingService;
use App\Services\CustomerPortalService;
use App\Services\PortalRecommendationService;
use App\Services\CapabilityService;
use App\Services\LoyaltyService;
use App\Services\CustomerSpecialistService;
use App\Services\BookingConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Wave6_2BCustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    private CustomerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CustomerResolver();
    }

    public function test_customer_resolves_through_customer_providers()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create(['phone' => '+1234567890']);
        $customer->providers()->attach($provider->id, ['id' => Str::uuid()]);

        $resolved = $this->resolver->findByContactForProvider('+1234567890', $provider->id);
        $this->assertNotNull($resolved);
        $this->assertEquals($customer->id, $resolved->id);
    }

    public function test_existing_customer_resolves_correctly_for_the_same_provider()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create(['phone' => '+1111111111']);
        $customer->providers()->attach($provider->id, ['id' => Str::uuid()]);

        $result = $this->resolver->resolveOrCreateForProvider([
            'phone' => '+1111111111',
            'name' => 'John Doe'
        ], $provider->id);

        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['is_new_provider_relationship']);
        $this->assertEquals($customer->id, $result['customer']->id);
    }

    public function test_same_customer_operates_across_multiple_locations_of_one_provider()
    {
        $provider = Provider::factory()->create();
        $location1 = Location::factory()->create(['provider_id' => $provider->id]);
        $location2 = Location::factory()->create(['provider_id' => $provider->id]);

        $result1 = $this->resolver->resolveOrCreateForProvider([
            'phone' => '+2222222222',
            'name' => 'Jane Doe'
        ], $provider->id); // From location 1 context

        $this->assertTrue($result1['is_new']);

        $result2 = $this->resolver->resolveOrCreateForProvider([
            'phone' => '+2222222222',
            'name' => 'Jane Doe'
        ], $provider->id); // From location 2 context

        $this->assertFalse($result2['is_new']);
        $this->assertFalse($result2['is_new_provider_relationship']);
        $this->assertEquals($result1['customer']->id, $result2['customer']->id);
        $this->assertEquals(1, $result2['customer']->providers()->count());
    }

    public function test_different_providers_remain_isolated()
    {
        $provider1 = Provider::factory()->create();
        $provider2 = Provider::factory()->create();

        $result1 = $this->resolver->resolveOrCreateForProvider([
            'phone' => '+3333333333',
            'name' => 'Isolated User'
        ], $provider1->id);

        $this->assertTrue($result1['is_new']);

        $result2 = $this->resolver->resolveOrCreateForProvider([
            'phone' => '+3333333333',
            'name' => 'Isolated User'
        ], $provider2->id);

        $this->assertFalse($result2['is_new']);
        $this->assertTrue($result2['is_new_provider_relationship']);
        $this->assertEquals($result1['customer']->id, $result2['customer']->id);
        
        $customer = $result2['customer']->fresh();
        $this->assertEquals(2, $customer->providers()->count());
    }

    public function test_portal_authentication_resolves_provider_context()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        $authService = new PortalAuthService(
            $this->createMock(CustomerPortalService::class),
            $this->createMock(PortalRecommendationService::class),
            $this->resolver,
            $this->createMock(CapabilityService::class),
            $this->createMock(LoyaltyService::class)
        );

        $customer = Customer::factory()->create(['phone' => '+4444444444', 'email' => 'test@example.com']);

        $result = $authService->register([
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '+4444444444',
            'location_id' => $location->id
        ]);

        $this->assertNotNull($result['portal_account']);
        $this->assertEquals(1, $customer->providers()->count());
        $this->assertEquals($provider->id, $customer->providers()->first()->id);

        // Test login
        $loginResult = $authService->login([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertNotNull($loginResult['token']);
        $this->assertEquals($provider->id, $loginResult['context']['active_provider']['id']);
    }

    public function test_customer_booking_resolves_provider_relationship_and_location_context()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $customer = Customer::factory()->create(['phone' => '+5555555555']);
        $service = Service::factory()->create(['price' => 100]);

        $bookingService = new CustomerBookingService(
            $this->createMock(CustomerSpecialistService::class),
            $this->createMock(BookingConfigurationService::class)
        );

        $booking = $bookingService->createBooking([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
        ]);

        $this->assertNotNull($booking);
        $this->assertEquals($location->id, $booking->location_id);
        $this->assertEquals($provider->id, $booking->provider_id);

        // Check if visits incremented in customer_providers
        $pivot = \DB::table('customer_providers')->where('customer_id', $customer->id)->where('provider_id', $provider->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(1, $pivot->visits);
    }
}
