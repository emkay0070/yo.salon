<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentRoutingService $routingService;
    private Salon $salon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingService = app(PaymentRoutingService::class);
        $this->salon = Salon::factory()->create();
    }

    public function test_routing_matrix_payment_method_explicit_account_takes_priority()
    {
        // Create MTN payment method with explicit MTN account
        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => false,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => $mtnAccount->id,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNotNull($result);
        $this->assertEquals($mtnAccount->id, $result->id);
        $this->assertEquals('mtn', $result->provider);
    }

    public function test_routing_matrix_falls_back_to_salon_default_provider_account()
    {
        // Create MTN payment method without explicit account
        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => null,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNotNull($result);
        $this->assertEquals($mtnAccount->id, $result->id);
        $this->assertEquals('mtn', $result->provider);
    }

    public function test_routing_matrix_falls_back_to_salon_default_account_any_provider()
    {
        // Create MTN payment method without explicit account, no MTN default
        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => null,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveAccount->id, $result->id);
        $this->assertEquals('flutterwave', $result->provider);
    }

    public function test_routing_matrix_cash_payment_returns_null()
    {
        // Cash payment method with no accounts
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'cash',
            'type' => 'cash',
            'payment_account_id' => null,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNull($result);
    }

    public function test_routing_matrix_card_explicit_account_overrides_default()
    {
        // Create two Flutterwave accounts
        $flutterwaveA = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => false,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $flutterwaveB = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'type' => 'card',
            'payment_account_id' => $flutterwaveA->id,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveA->id, $result->id);
    }

    public function test_routing_matrix_card_without_explicit_uses_default()
    {
        // Create Flutterwave default account
        $flutterwaveB = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'type' => 'card',
            'payment_account_id' => null,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveB->id, $result->id);
    }

    public function test_routing_edge_case_suspended_account_is_skipped()
    {
        // Create suspended MTN account and active Flutterwave account
        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => false, // Suspended
            'verification_status' => 'verified',
        ]);

        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => $mtnAccount->id,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        // Should skip suspended account and fall back to Flutterwave
        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveAccount->id, $result->id);
    }

    public function test_routing_edge_case_unverified_account_is_skipped()
    {
        // Create unverified MTN account and verified Flutterwave account
        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'pending', // Unverified
        ]);

        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => $mtnAccount->id,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        // Should skip unverified account and fall back to Flutterwave
        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveAccount->id, $result->id);
    }

    public function test_routing_edge_case_no_usable_accounts_returns_null()
    {
        // Create only suspended/unverified accounts
        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => false,
            'verification_status' => 'pending',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => null,
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        // Should return null when no usable accounts exist
        $this->assertNull($result);
    }

    public function test_routing_edge_case_wrong_provider_account_is_skipped()
    {
        // PaymentMethod is MTN but explicit account is Flutterwave
        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => false,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'payment_account_id' => $flutterwaveAccount->id, // Wrong provider
        ]);

        $result = $this->routingService->determinePaymentAccount($paymentMethod);

        // Should skip wrong provider account and use MTN default
        $this->assertNotNull($result);
        $this->assertEquals($mtnAccount->id, $result->id);
        $this->assertEquals('mtn', $result->provider);
    }

    public function test_manual_payment_uses_default_account()
    {
        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $result = $this->routingService->determineAccountForManualPayment($this->salon);

        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveAccount->id, $result->id);
    }

    public function test_manual_payment_with_provider_hint_uses_provider_default()
    {
        $mtnAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $result = $this->routingService->determineAccountForManualPayment($this->salon, 'mtn');

        $this->assertNotNull($result);
        $this->assertEquals($mtnAccount->id, $result->id);
        $this->assertEquals('mtn', $result->provider);
    }

    public function test_get_available_accounts_returns_only_active_verified()
    {
        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'is_active' => false, // Suspended
            'verification_status' => 'verified',
        ]);

        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'airtel',
            'is_active' => true,
            'verification_status' => 'pending', // Unverified
        ]);

        $accounts = $this->routingService->getAvailableAccounts($this->salon);

        $this->assertCount(1, $accounts);
        $this->assertEquals('mtn', $accounts->first()->provider);
    }

    public function test_get_default_account_returns_salon_default()
    {
        $flutterwaveAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn',
            'is_default' => false,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $result = $this->routingService->getDefaultAccount($this->salon);

        $this->assertNotNull($result);
        $this->assertEquals($flutterwaveAccount->id, $result->id);
        $this->assertTrue($result->is_default);
    }
}
