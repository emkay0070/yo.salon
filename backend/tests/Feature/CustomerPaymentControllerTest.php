<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PaymentMethod;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CustomerPaymentControllerTest
 *
 * Focused verification of the public customer Flutterwave payment
 * initialization endpoint (POST /api/payments/customer/initialize).
 *
 * Covers:
 * - Successful Flutterwave payment initialization for a guest booking
 * - Rejection when the payment method is not Flutterwave
 * - Rejection when the payment method does not belong to the booking's provider
 * - Rejection when the payment method is inactive
 * - Payment status lookup (GET /api/payments/customer/status)
 */
class CustomerPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationService $organizationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationService = app(OrganizationService::class);

        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );
    }

    protected function createOwnerAndProvider(string $orgName, string $providerName): array
    {
        $owner = User::factory()->create();
        $org = $this->organizationService->createForBusinessOwner($owner, ['name' => $orgName]);

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'provider_type_id' => ProviderType::byKey('salon')->first()->id,
            'display_name'     => $providerName,
            'slug'             => Str::slug($providerName),
            'status'           => 'active',
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $location = Location::create([
            'provider_id' => $provider->id,
            'name'        => $providerName . ' Location',
            'slug'        => Str::slug($providerName . '-location'),
            'address'     => '123 Test Street',
            'city'        => 'Kampala',
            'country'     => 'UG',
            'is_primary'  => true,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $customer = \App\Models\Customer::createGuest($provider->id, 'Test Customer');

        return ['owner' => $owner, 'org' => $org, 'provider' => $provider, 'location' => $location, 'customer' => $customer];
    }

    protected function createFlutterwavePaymentMethod(Provider $provider): PaymentMethod
    {
        return PaymentMethod::create([
            'provider_id'       => $provider->id,
            'provider'          => 'flutterwave',
            'type'              => 'gateway',
            'display_name'      => 'Flutterwave Checkout',
            'account_identifier' => 'FLWPUBK_TEST123',
            'metadata'          => ['secret' => 'FLWSECK_TEST123'],
            'currency'          => 'UGX',
            'is_primary'        => true,
            'is_active'         => true,
            'supports_online_payment' => true,
        ]);
    }

    protected function createBooking(Provider $provider, Location $location, Customer $customer): Booking
    {
        $service = Service::create([
            'provider_id'      => $provider->id,
            'location_id'      => $location->id,
            'name'             => 'Test Haircut',
            'price'            => 50000,
            'duration'         => 30,
            'active'           => true,
        ]);

        return Booking::create([
            'provider_id'       => $provider->id,
            'location_id'       => $location->id,
            'customer_id'       => $customer->id,
            'service_id'        => $service->id,
            'date'              => now()->addDay()->toDateString(),
            'time'              => '10:00',
            'status'            => 'pending_payment',
            'payment_status'    => 'pending',
            'total_price'       => 50000,
            'amount_due'        => 50000,
        ]);
    }

    #[Test]
    public function guest_can_initialize_flutterwave_payment_for_booking(): void
    {
        Http::fake([
            'https://idp.flutterwave.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            '*/customers*' => Http::response([
                'data' => [
                    ['id' => 'flw_cust_123', 'email' => 'customer@example.com'],
                ],
            ], 200),
            '*/payment-methods' => Http::response([
                'data' => [
                    'id' => 'flw_pm_123',
                ],
            ], 200),
            '*/charges' => Http::response([
                'data' => [
                    'id' => 'flw_chg_123',
                    'next_action' => [
                        'redirect_url' => [
                            'url' => 'https://checkout.flutterwave.com/pay/mock123',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fixtures = $this->createOwnerAndProvider('Alpha Salon', 'Alpha Provider');
        $provider = $fixtures['provider'];
        $location = $fixtures['location'];
        $customer = $fixtures['customer'];

        $paymentMethod = $this->createFlutterwavePaymentMethod($provider);
        $booking = $this->createBooking($provider, $location, $customer);

        $response = $this->postJson('/api/payments/customer/initialize', [
            'booking_id'       => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'email'            => 'customer@example.com',
            'customer_name'    => 'Test Customer',
            'customer_phone'   => '+256700000000',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment_link', 'https://checkout.flutterwave.com/pay/mock123')
            ->assertJsonPath('provider_reference', 'flw_chg_123');
    }

    #[Test]
    public function non_flutterwave_payment_method_is_rejected(): void
    {
        $fixtures = $this->createOwnerAndProvider('Beta Salon', 'Beta Provider');
        $provider = $fixtures['provider'];
        $location = $fixtures['location'];
        $customer = $fixtures['customer'];

        $cashMethod = PaymentMethod::create([
            'provider_id'       => $provider->id,
            'provider'          => 'cash',
            'type'              => 'cash',
            'display_name'      => 'Cash',
            'currency'          => 'UGX',
            'is_primary'        => true,
            'is_active'         => true,
            'supports_online_payment' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $booking = $this->createBooking($provider, $location, $customer);

        $response = $this->postJson('/api/payments/customer/initialize', [
            'booking_id'       => $booking->id,
            'payment_method_id' => $cashMethod->id,
            'email'            => 'customer@example.com',
            'customer_name'    => 'Test Customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function payment_method_from_different_provider_is_rejected(): void
    {
        $fixturesA = $this->createOwnerAndProvider('Salon A', 'Provider A');
        $fixturesB = $this->createOwnerAndProvider('Salon B', 'Provider B');

        $providerA = $fixturesA['provider'];
        $locationA = $fixturesA['location'];
        $customerA = $fixturesA['customer'];

        $providerB = $fixturesB['provider'];

        // Flutterwave method belonging to Provider B
        $paymentMethodB = $this->createFlutterwavePaymentMethod($providerB);

        // Booking belonging to Provider A
        $booking = $this->createBooking($providerA, $locationA, $customerA);

        $response = $this->postJson('/api/payments/customer/initialize', [
            'booking_id'       => $booking->id,
            'payment_method_id' => $paymentMethodB->id,
            'email'            => 'customer@example.com',
            'customer_name'    => 'Test Customer',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function inactive_payment_method_is_rejected(): void
    {
        $fixtures = $this->createOwnerAndProvider('Gamma Salon', 'Gamma Provider');
        $provider = $fixtures['provider'];
        $location = $fixtures['location'];
        $customer = $fixtures['customer'];

        $paymentMethod = $this->createFlutterwavePaymentMethod($provider);
        $paymentMethod->update(['is_active' => false]);

        $booking = $this->createBooking($provider, $location, $customer);

        $response = $this->postJson('/api/payments/customer/initialize', [
            'booking_id'       => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'email'            => 'customer@example.com',
            'customer_name'    => 'Test Customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function customer_can_lookup_payment_status(): void
    {
        $fixtures = $this->createOwnerAndProvider('Delta Salon', 'Delta Provider');
        $provider = $fixtures['provider'];
        $location = $fixtures['location'];
        $customer = $fixtures['customer'];

        $paymentMethod = $this->createFlutterwavePaymentMethod($provider);
        $booking = $this->createBooking($provider, $location, $customer);

        $response = $this->getJson('/api/payments/customer/status?booking_id=' . $booking->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking_id', $booking->id)
            ->assertJsonPath('payment_status', 'pending');
    }

    #[Test]
    public function unknown_booking_returns_404(): void
    {
        $response = $this->getJson('/api/payments/customer/status?booking_id=' . Str::uuid());

        $response->assertStatus(404);
    }
}
