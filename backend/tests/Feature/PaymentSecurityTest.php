<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Provider;
use App\Models\ProviderUser;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Provider $otherProvider;
    private User $owner;
    private PaymentMethod $paymentMethod;
    private PaymentMethod $otherPaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->otherProvider = Provider::factory()->create();

        $this->owner = User::factory()->create(['status' => 'active']);
        ProviderUser::factory()->create([
            'user_id' => $this->owner->id,
            'provider_id' => $this->provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'provider_id' => $this->provider->id,
            'provider' => 'mtn_momo',
            'type' => 'mobile_money',
        ]);

        $this->otherPaymentMethod = PaymentMethod::factory()->create([
            'provider_id' => $this->otherProvider->id,
            'provider' => 'mtn_momo',
            'type' => 'mobile_money',
        ]);
    }

    private function request(): self
    {
        $token = $this->owner->createToken('payment-security-test')->plainTextToken;

        return $this->withToken($token)
            ->withHeaders(['X-Provider-Id' => $this->provider->id]);
    }

    private function makePaymentRequest(array $overrides = []): PaymentRequest
    {
        return PaymentRequest::create(array_merge([
            'provider_id' => $this->provider->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'status' => 'sent',
            'provider_reference' => 'PAY-' . uniqid(),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ], $overrides));
    }

    public function test_provider_cannot_read_another_providers_payment_request(): void
    {
        $foreign = $this->makePaymentRequest([
            'provider_id' => $this->otherProvider->id,
            'payment_method_id' => $this->otherPaymentMethod->id,
        ]);

        $this->request()
            ->getJson("/api/v1/payment-requests/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_provider_cannot_cancel_another_providers_payment_request(): void
    {
        $foreign = $this->makePaymentRequest([
            'provider_id' => $this->otherProvider->id,
            'payment_method_id' => $this->otherPaymentMethod->id,
        ]);

        $this->request()
            ->postJson("/api/v1/payment-requests/{$foreign->id}/cancel")
            ->assertStatus(404);

        $this->assertDatabaseHas('payment_requests', [
            'id' => $foreign->id,
            'status' => 'sent',
        ]);
    }

    public function test_paid_status_cannot_be_set_through_status_endpoint(): void
    {
        $paymentRequest = $this->makePaymentRequest();

        $this->request()
            ->patchJson("/api/v1/payment-requests/{$paymentRequest->id}/status", [
                'status' => 'paid',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_idempotency_key_is_persisted_and_reused(): void
    {
        $payload = [
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 5000,
            'idempotency_key' => 'checkout-attempt-1',
        ];

        $first = $this->request()->postJson('/api/v1/payment-requests', $payload);
        $first->assertStatus(201);

        $second = $this->request()->postJson('/api/v1/payment-requests', $payload);
        $second->assertStatus(200);

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('payment_requests', 1);
        $this->assertDatabaseHas('payment_requests', [
            'provider_id' => $this->provider->id,
            'idempotency_key' => 'checkout-attempt-1',
        ]);
    }

    public function test_payment_method_update_encrypts_credentials_at_rest(): void
    {
        $this->request()
            ->putJson("/api/v1/payment-methods/{$this->paymentMethod->id}", [
                'api_key' => 'super-secret-api-key',
                'api_subscription_key' => 'super-secret-subscription-key',
            ])
            ->assertStatus(200);

        $rawApiKey = DB::table('payment_methods')
            ->where('id', $this->paymentMethod->id)
            ->value('api_key');

        $this->assertNotSame('super-secret-api-key', $rawApiKey);
        $this->assertNotEmpty($rawApiKey);

        $fresh = PaymentMethod::find($this->paymentMethod->id);
        $this->assertSame('super-secret-api-key', $fresh->api_key);
        $this->assertSame('super-secret-subscription-key', $fresh->api_subscription_key);
    }

    public function test_provider_cannot_update_another_providers_payment_method(): void
    {
        $this->request()
            ->putJson("/api/v1/payment-methods/{$this->otherPaymentMethod->id}", [
                'display_name' => 'Hijacked',
            ])
            ->assertStatus(404);

        $this->assertDatabaseMissing('payment_methods', [
            'id' => $this->otherPaymentMethod->id,
            'display_name' => 'Hijacked',
        ]);
    }

    public function test_refund_cannot_exceed_remaining_net_amount(): void
    {
        $transaction = Transaction::create([
            'provider_id' => $this->provider->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 5000,
            'gateway_fee' => 140,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 4860,
            'currency' => 'UGX',
            'internal_reference' => 'TXN-' . uniqid(),
            'provider_reference' => 'MANUAL-' . uniqid(),
            'paid_at' => now(),
        ]);

        $service = app(BookingPaymentService::class);

        try {
            $service->refundBookingPayment($transaction->id, 5000);
            $this->fail('Refunding more than the captured net amount must fail');
        } catch (\Exception $e) {
            $this->assertStringContainsString('exceeds', $e->getMessage());
        }

        $service->refundBookingPayment($transaction->id, 2000);
        $service->refundBookingPayment($transaction->id, 2860);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
        ]);
        $this->assertTrue((bool) Transaction::find($transaction->id)->metadata['refunded']);

        $this->expectException(\Exception::class);
        $service->refundBookingPayment($transaction->id, 1);
    }
}
