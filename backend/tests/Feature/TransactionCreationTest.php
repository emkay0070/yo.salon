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

class TransactionCreationTest extends TestCase
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
            'price' => 5000
        ]);
        
        $this->booking = Booking::factory()->create([
            'salon_id' => $this->salon->id,
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid'
        ]);
        
        // Attach service to booking to give it a total price of 5000
        $this->booking->services()->attach($service->id);
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'flutterwave',
            'type' => 'card'
        ]);
        
        // Make sure there is a default account for routing
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

    public function test_initialize_digital_payment_creates_request(): void
    {
        $this->providerMock->shouldReceive('initializePayment')
            ->once()
            ->andReturn([
                'success' => true,
                'provider_reference' => 'FLW-123',
                'payment_link' => 'https://link.com'
            ]);

        $result = $this->salonPaymentService->initializeBookingPayment(
            $this->booking->id,
            $this->paymentMethod->id,
            $this->customer->email ?? 'test@example.com',
            $this->customer->name
        );

        $this->assertTrue($result['success']);
        
        $this->assertDatabaseHas('payment_requests', [
            'booking_id' => $this->booking->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'sent',
            'provider_reference' => 'FLW-123',
            'amount' => 5000
        ]);
        
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'pending'
        ]);
    }

    public function test_verify_digital_payment_creates_transaction(): void
    {
        // First create a pending payment request
        PaymentRequest::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
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
                'fees' => 140 // Actual provider fee
            ]);

        $result = $this->salonPaymentService->verifySalonPayment('FLW-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);
        
        // Check transaction economic fields
        $this->assertDatabaseHas('transactions', [
            'booking_id' => $this->booking->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 5000,
            'gateway_fee' => 140, // Should come from provider mock
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 4860 // 5000 - 140
        ]);
        
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'paid'
        ]);
        
        $this->assertDatabaseHas('payment_requests', [
            'provider_reference' => 'FLW-123',
            'status' => 'paid'
        ]);
    }
    
    public function test_verify_idempotent_payment(): void
    {
        // Create an ALREADY paid request and transaction
        $paymentRequest = PaymentRequest::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'paid',
            'provider_reference' => 'FLW-123',
            'requested_at' => now(),
        ]);
        
        Transaction::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 5000,
            'gateway_fee' => 140,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 4860,
            'internal_reference' => 'TXN-123',
            'provider_reference' => 'FLW-123',
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-123')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
            ]);

        $result = $this->salonPaymentService->verifySalonPayment('FLW-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('already_paid', $result['status']);
        
        // Assert only ONE transaction exists
        $this->assertEquals(1, Transaction::where('provider_reference', 'FLW-123')->count());
    }

    public function test_record_manual_payment(): void
    {
        // This doesn't call the provider mock at all
        $result = $this->salonPaymentService->recordManualPayment([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'notes' => 'Paid at front desk'
        ]);

        $this->assertTrue($result['success']);
        
        $this->assertDatabaseHas('transactions', [
            'booking_id' => $this->booking->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 5000,
            'gateway_fee' => 0, // Manual = no fees
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 5000
        ]);
        
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'paid'
        ]);
    }

    public function test_refund_manual_payment(): void
    {
        // First create manual transaction
        $result = $this->salonPaymentService->recordManualPayment([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_method' => 'cash'
        ]);
        
        $transaction = $result['transaction'];
        
        // Now refund it (no provider call expected)
        $refundResult = $this->salonPaymentService->refundSalonPayment($transaction->id);
        
        $this->assertTrue($refundResult['success']);
        
        $this->assertDatabaseHas('transactions', [
            'booking_id' => $this->booking->id,
            'type' => 'refund',
            'status' => 'completed',
            'gross_amount' => -5000,
            'gateway_fee' => 0,
            'net_amount' => -5000
        ]);
    }
}
