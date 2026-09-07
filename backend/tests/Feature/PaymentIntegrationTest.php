<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected Customer $customer;
    protected Booking $booking;
    protected PaymentMethod $paymentMethod;
    protected PaymentRequest $paymentRequest;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->salon = Salon::factory()->create();
        $this->customer = Customer::factory()->create();
        
        $this->booking = Booking::factory()->create([
            'salon_id' => $this->salon->id,
            'customer_id' => $this->customer->id,
            'payment_status' => 'pending'
        ]);
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'provider' => 'mtn_momo',
            'type' => 'mobile_money'
        ]);

        $this->paymentRequest = PaymentRequest::create([
            'salon_id' => $this->salon->id,
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

        // MTN doesn't mandate signature in this mock setup if validateWebhookSignature is permissive
        $response = $this->postJson('/api/v1/webhooks/mtn', $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'MTN webhook processed successfully']);

        // Check Payment Request was updated
        $this->assertDatabaseHas('payment_requests', [
            'id' => $this->paymentRequest->id,
            'status' => 'successful',
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
}
