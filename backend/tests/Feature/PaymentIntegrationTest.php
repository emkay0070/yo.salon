<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected Location $location;
    protected Customer $customer;
    protected Booking $booking;
    protected PaymentMethod $paymentMethod;
    protected PaymentRequest $paymentRequest;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->customer = Customer::factory()->create();
        
        $this->booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'payment_status' => 'pending'
        ]);
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'provider_id' => $this->provider->id,
            'provider' => 'mtn_momo',
            'type' => 'mobile_money',
            'merchant_id' => 'test-api-user',
            'api_key' => 'test-api-key',
            'api_subscription_key' => 'test-subscription-key',
            'environment' => 'sandbox',
        ]);

        $this->paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'MTN-12345',
            'requested_at' => now(),
        ]);
    }

    public function test_mtn_webhook_handles_successful_payment(): void
    {
        $payload = [
            'externalId' => 'MTN-12345',
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'FT-987654',
            'amount' => 5000,
            'currency' => 'UGX',
        ];

        // MTN callbacks are unsigned; the handler must confirm the final status
        // with the MTN API instead of trusting the callback payload.
        Http::fake([
            'sandbox.momodeveloper.mtn.com/collection/token/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/*' => Http::response([
                'status' => 'SUCCESSFUL',
                'financialTransactionId' => 'FT-987654',
                'amount' => '5000',
                'currency' => 'UGX',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/webhooks/mtn', $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'MTN webhook processed successfully']);

        // Check Payment Request was updated
        $this->assertDatabaseHas('payment_requests', [
            'id' => $this->paymentRequest->id,
            'status' => 'paid',
            'provider_reference' => 'MTN-12345'
        ]);
        
        // Check Transaction was created
        $this->assertDatabaseHas('transactions', [
            'booking_id' => $this->booking->id,
            'status' => 'completed',
            'type' => 'payment',
            'gross_amount' => 5000
        ]);

        // Check Booking was marked paid
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'paid'
        ]);
    }
    
    public function test_mtn_webhook_handles_failed_payment(): void
    {
        $payload = [
            'externalId' => 'MTN-12345',
            'status' => 'FAILED',
            'financialTransactionId' => 'FT-987654',
        ];

        $response = $this->postJson('/api/v1/webhooks/mtn', $payload);

        $response->assertStatus(200);

        // Check Payment Request was updated to failed
        $this->assertDatabaseHas('payment_requests', [
            'id' => $this->paymentRequest->id,
            'status' => 'failed',
        ]);
        
        // Transaction should NOT be created
        $this->assertDatabaseMissing('transactions', [
            'booking_id' => $this->booking->id,
        ]);

        // Booking remains pending (or unpaid)
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'pending'
        ]);
    }

    public function test_mtn_webhook_does_not_settle_while_provider_reports_pending(): void
    {
        Http::fake([
            'sandbox.momodeveloper.mtn.com/collection/token/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/*' => Http::response([
                'status' => 'PENDING',
                'amount' => '5000',
                'currency' => 'UGX',
            ], 200),
        ]);

        // The callback claims success, but MTN says the payment is still pending.
        $response = $this->postJson('/api/v1/webhooks/mtn', [
            'externalId' => 'MTN-12345',
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'FT-987654',
            'amount' => 5000,
            'currency' => 'UGX',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_requests', [
            'id' => $this->paymentRequest->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseMissing('transactions', [
            'booking_id' => $this->booking->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'payment_status' => 'pending',
        ]);
    }
}
