<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Models\Salon;
use App\Models\Transaction;
use App\Models\PaymentRequest;
use App\Models\Service;
use App\Services\FeeEngine;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentRoutingService;
use App\Services\Payments\SalonPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class PaymentToFinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected Customer $customer;
    protected Booking $booking;
    protected PaymentMethod $paymentMethod;
    protected SalonPaymentService $salonPaymentService;
    protected $providerMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->salon = Salon::factory()->create();
        $this->customer = Customer::factory()->create();
        
        $service = Service::factory()->create([
            'provider_id' => $this->salon->provider_id,
            'price' => 3000
        ]);
        
        $this->booking = Booking::factory()->create([
            'salon_id' => $this->salon->id,
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid'
        ]);
        
        $this->booking->services()->attach($service->id);
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'type' => 'card'
        ]);
        
        PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $this->providerMock = Mockery::mock(PaymentProviderInterface::class);
        $this->app->instance(PaymentProviderInterface::class, $this->providerMock);
        
        $this->salonPaymentService = new SalonPaymentService(
            $this->providerMock,
            app(FeeEngine::class),
            app(PaymentRoutingService::class)
        );
    }

    public function test_end_to_end_payment_creates_transaction_with_correct_economics(): void
    {
        // Step 1: Initialize payment
        $this->providerMock->shouldReceive('initializePayment')
            ->once()
            ->andReturn([
                'success' => true,
                'provider_reference' => 'FLW-123',
                'payment_link' => 'https://link.com'
            ]);

        $initResult = $this->salonPaymentService->initializeBookingPayment(
            $this->booking->id,
            $this->paymentMethod->id,
            $this->customer->email ?? 'test@example.com',
            $this->customer->name
        );

        $this->assertTrue($initResult['success']);
        
        // Step 2: Verify payment with actual gateway fee
        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-123')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-123',
                'fees' => 84 // 2.8% of 3000
            ]);

        $verifyResult = $this->salonPaymentService->verifySalonPayment('FLW-123');

        $this->assertTrue($verifyResult['success']);
        
        // Step 3: Verify Transaction economics
        $transaction = Transaction::where('provider_reference', 'FLW-123')->first();
        $this->assertNotNull($transaction);
        
        $this->assertEquals(3000, $transaction->gross_amount);
        $this->assertEquals(84, $transaction->gateway_fee);
        $this->assertEquals(0, $transaction->platform_fee);
        $this->assertEquals(0, $transaction->tax_amount);
        $this->assertEquals(2916, $transaction->net_amount); // 3000 - 84
        
        // Step 4: Verify PaymentAccount was routed
        $this->assertNotNull($transaction->payment_account_id);
        
        // Step 5: Verify booking status updated
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'paid'
        ]);
    }

    public function test_manual_payment_creates_transaction_with_correct_economics(): void
    {
        // Manual payment (no provider call)
        $result = $this->salonPaymentService->recordManualPayment([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 3000,
            'payment_method' => 'cash',
            'notes' => 'Paid at front desk'
        ]);

        $this->assertTrue($result['success']);
        
        $transaction = $result['transaction'];
        
        // Verify transaction economics (cash = no fees)
        $this->assertEquals(3000, $transaction->gross_amount);
        $this->assertEquals(0, $transaction->gateway_fee);
        $this->assertEquals(0, $transaction->platform_fee);
        $this->assertEquals(0, $transaction->tax_amount);
        $this->assertEquals(3000, $transaction->net_amount);
    }

    public function test_refund_creates_transaction_with_negative_economics(): void
    {
        // First create a payment
        PaymentRequest::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 3000,
            'status' => 'sent',
            'provider_reference' => 'FLW-123',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-123')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-123',
                'fees' => 84
            ]);

        $this->salonPaymentService->verifySalonPayment('FLW-123');
        
        $originalTransaction = Transaction::where('provider_reference', 'FLW-123')->first();
        
        // Now refund
        $this->providerMock->shouldReceive('refundPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'reference' => 'REF-123'
            ]);
        
        $refundResult = $this->salonPaymentService->refundSalonPayment($originalTransaction->id);
        
        $this->assertTrue($refundResult['success']);
        
        // Verify refund transaction
        $refundTransaction = Transaction::where('provider_reference', 'REF-123')->first();
        $this->assertNotNull($refundTransaction);
        $this->assertEquals('refund', $refundTransaction->type);
        $this->assertEquals(-2916, $refundTransaction->gross_amount); // Refunds net amount (what customer actually paid)
        $this->assertEquals(0, $refundTransaction->gateway_fee);
        $this->assertEquals(0, $refundTransaction->platform_fee);
        $this->assertEquals(-2916, $refundTransaction->net_amount);
        
        // Verify refund inherits payment_account_id from original
        $this->assertEquals($originalTransaction->payment_account_id, $refundTransaction->payment_account_id);
    }

    public function test_payment_account_id_is_recorded_in_transaction(): void
    {
        // Create explicit payment account
        $paymentAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => false,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $this->paymentMethod->update(['payment_account_id' => $paymentAccount->id]);

        PaymentRequest::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 3000,
            'status' => 'sent',
            'provider_reference' => 'FLW-123',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-123',
                'fees' => 84
            ]);

        $this->salonPaymentService->verifySalonPayment('FLW-123');
        
        $transaction = Transaction::where('provider_reference', 'FLW-123')->first();
        
        $this->assertEquals($paymentAccount->id, $transaction->payment_account_id);
    }

    public function test_zero_platform_fee_maintained_through_full_flow(): void
    {
        PaymentRequest::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 50000, // Large amount
            'status' => 'sent',
            'provider_reference' => 'FLW-123',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-123',
                'fees' => 1400 // 2.8% of 50000
            ]);

        $this->salonPaymentService->verifySalonPayment('FLW-123');
        
        $transaction = Transaction::where('provider_reference', 'FLW-123')->first();
        
        // Verify zero platform fee regardless of amount
        $this->assertEquals(50000, $transaction->gross_amount);
        $this->assertEquals(1400, $transaction->gateway_fee);
        $this->assertEquals(0, $transaction->platform_fee);
        $this->assertEquals(48600, $transaction->net_amount);
    }
}
