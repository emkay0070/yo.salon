<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\PaymentRequest;
use App\Models\Service;
use App\Services\FeeEngine;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentRoutingService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class TransactionCreationTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected Location $location;
    protected Customer $customer;
    protected Booking $booking;
    protected PaymentMethod $paymentMethod;
    protected BookingPaymentService $bookingPaymentService;
    protected $providerMock;
    protected $paymentManagerMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->customer = Customer::factory()->create();
        
        $service = Service::factory()->create([
            'provider_id' => $this->provider->id,
            'price' => 5000
        ]);
        
        $this->booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid'
        ]);
        
        // Attach service to booking to give it a total price of 5000
        $this->booking->services()->attach($service->id);
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'provider_id' => $this->provider->id,
            'provider' => 'flutterwave',
            'type' => 'card'
        ]);
        
        // Make sure there is a default account for routing
        PaymentAccount::factory()->create([
            'provider_id' => $this->provider->id,
            'provider' => 'flutterwave',
            'account_type' => 'merchant',
            'is_default' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $this->providerMock = Mockery::mock(\App\Services\Payments\Contracts\PaymentProviderInterface::class);
        $this->app->instance(\App\Services\Payments\Contracts\PaymentProviderInterface::class, $this->providerMock);

        // Mock PaymentManager to return the provider mock when getProvider('flutterwave') is called
        $this->paymentManagerMock = Mockery::mock(PaymentManager::class);
        $this->paymentManagerMock->shouldReceive('getProvider')->with('flutterwave')->andReturn($this->providerMock);
        $this->app->instance(PaymentManager::class, $this->paymentManagerMock);

        $this->bookingPaymentService = new BookingPaymentService(
            $this->paymentManagerMock,
            app(FeeEngine::class),
            app(PaymentRoutingService::class),
            app(\App\Services\BookingConfigurationService::class)
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

        $result = $this->bookingPaymentService->initializeBookingPayment(
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
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
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

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-123');

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
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'paid',
            'provider_reference' => 'FLW-123',
            'requested_at' => now(),
        ]);
        
        Transaction::create([
            'provider_id' => $this->provider->id,
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

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('already_paid', $result['status']);
        
        // Assert only ONE transaction exists
        $this->assertEquals(1, Transaction::where('provider_reference', 'FLW-123')->count());
    }

    public function test_record_manual_payment(): void
    {
        // This doesn't call the provider mock at all
        $result = $this->bookingPaymentService->recordManualPayment([
            'location_id' => $this->location->id,
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
        $result = $this->bookingPaymentService->recordManualPayment([
            'location_id' => $this->location->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_method' => 'cash'
        ]);
        
        $transaction = $result['transaction'];
        
        // Now refund it (no provider call expected)
        $refundResult = $this->bookingPaymentService->refundBookingPayment($transaction->id);
        
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

    public function test_successful_payment_confirms_booking_without_approval(): void
    {
        // Create booking in pending_payment status
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
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
                'fees' => 140
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);

        // Booking should be confirmed since no approval is required by default
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);
    }

    public function test_successful_payment_moves_to_pending_with_manual_approval(): void
    {
        // Create service with manual approval requirement
        $service = Service::factory()->create(['provider_id' => $this->provider->id]);

        // Create booking in pending_payment status
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => $service->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-456',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-456')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-456',
                'fees' => 140
            ]);

        // Mock config service to return manual approval requirement
        $configMock = Mockery::mock(\App\Services\BookingConfigurationService::class);
        $configMock->shouldReceive('requiresApproval')
            ->once()
            ->with($service->id, $this->provider->id, $this->location->id)
            ->andReturn(true);
        $this->app->instance(\App\Services\BookingConfigurationService::class, $configMock);

        // Recreate service with mocked config
        $this->bookingPaymentService = new BookingPaymentService(
            $this->paymentManagerMock,
            app(FeeEngine::class),
            app(PaymentRoutingService::class),
            $configMock
        );

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-456');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);

        // Booking should move to pending since approval is required
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
            'payment_status' => 'paid'
        ]);
    }

    public function test_idempotent_payment_verification(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-789',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->twice()
            ->with('FLW-789')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-789',
                'fees' => 140
            ]);

        // First verification
        $result1 = $this->bookingPaymentService->verifyBookingPayment('FLW-789');
        $this->assertTrue($result1['success']);

        // Second verification (idempotent)
        $result2 = $this->bookingPaymentService->verifyBookingPayment('FLW-789');
        $this->assertTrue($result2['success']);
        $this->assertEquals('already_paid', $result2['status']);

        // Should only have one transaction
        $transactionCount = Transaction::where('booking_id', $booking->id)->count();
        $this->assertEquals(1, $transactionCount);

        // Booking should still be confirmed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);
    }

    public function test_failed_payment_does_not_confirm_booking(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-999',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-999')
            ->andReturn([
                'success' => false,
                'status' => 'failed',
                'provider_reference' => 'FLW-999',
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-999');

        $this->assertFalse($result['success']);

        // Booking should remain in pending_payment status
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid'
        ]);

        // No transaction should be created
        $transactionCount = Transaction::where('booking_id', $booking->id)->count();
        $this->assertEquals(0, $transactionCount);
    }

    public function test_payment_after_expiry_is_rejected(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-LATE',
            'requested_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-LATE')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-LATE',
                'fees' => 140
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-LATE');

        $this->assertFalse($result['success']);
        $this->assertEquals('expired', $result['status']);

        // Booking should remain in pending_payment status
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid'
        ]);

        // Payment request should be marked expired
        $this->assertDatabaseHas('payment_requests', [
            'provider_reference' => 'FLW-LATE',
            'status' => 'expired',
        ]);

        // No transaction should be created
        $transactionCount = Transaction::where('booking_id', $booking->id)->count();
        $this->assertEquals(0, $transactionCount);
    }

    public function test_payment_for_cancelled_booking_is_rejected(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'cancelled', // Already cancelled
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-CANCELLED',
            'requested_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-CANCELLED')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-CANCELLED',
                'fees' => 140
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-CANCELLED');

        $this->assertFalse($result['success']);
        $this->assertEquals('booking_cancelled', $result['status']);

        // Booking should remain cancelled
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'payment_status' => 'unpaid'
        ]);

        // No transaction should be created
        $transactionCount = Transaction::where('booking_id', $booking->id)->count();
        $this->assertEquals(0, $transactionCount);
    }

    public function test_payment_before_expiry_succeeds(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'FLW-ONTIME',
            'requested_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(10), // Not expired yet
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-ONTIME')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-ONTIME',
                'fees' => 140
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-ONTIME');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);

        // Booking should be confirmed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);
    }

    public function test_payment_already_marked_expired_is_rejected(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => Service::factory()->create(['provider_id' => $this->provider->id])->id,
        ]);

        PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'expired', // Already marked expired
            'provider_reference' => 'FLW-EXPIRED',
            'requested_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);

        $this->providerMock->shouldReceive('verifyPayment')
            ->once()
            ->with('FLW-EXPIRED')
            ->andReturn([
                'success' => true,
                'status' => 'successful',
                'provider_reference' => 'FLW-EXPIRED',
                'fees' => 140
            ]);

        $result = $this->bookingPaymentService->verifyBookingPayment('FLW-EXPIRED');

        $this->assertFalse($result['success']);
        $this->assertEquals('expired', $result['status']);

        // Booking should remain in pending_payment status
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid'
        ]);

        // No transaction should be created
        $transactionCount = Transaction::where('booking_id', $booking->id)->count();
        $this->assertEquals(0, $transactionCount);
    }
}
