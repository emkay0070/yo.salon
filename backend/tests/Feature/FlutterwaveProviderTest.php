<?php

namespace Tests\Feature;

use App\Services\Payments\FlutterwaveProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlutterwaveProviderTest extends TestCase
{
    protected FlutterwaveProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.flutterwave.client_id', 'test_client_id');
        Config::set('services.flutterwave.client_secret', 'test_client_secret');
        Config::set('services.flutterwave.public_key', 'test_public_key');
        Config::set('services.flutterwave.webhook_secret', 'test_webhook_secret');
        Config::set('services.flutterwave.base_url', 'https://developersandbox-api.flutterwave.com');

        $this->provider = new FlutterwaveProvider();
    }

    /**
     * Helper: mock the full v4 payment flow (token + customer + payment method + charge).
     */
    protected function mockV4Flow(
        string $accessToken = 'test_access_token',
        string $customerId = 'cus_test123',
        string $paymentMethodId = 'pmd_test123',
        array $chargeData = []
    ): void {
        $defaultCharge = [
            'id' => 'chg_test123',
            'amount' => 5000,
            'currency' => 'UGX',
            'reference' => 'BOOKING-TEST-123',
            'customer_id' => $customerId,
            'status' => 'pending',
            'processor_response' => ['type' => 'pending', 'code' => '02', 'message' => 'Pending'],
            'created_datetime' => '2026-01-01T10:00:00.000Z',
            'next_action' => [
                'type' => 'redirect_url',
                'redirect_url' => [
                    'url' => 'https://developersandbox.flutterwave.com/redirects?card&token=abc123',
                ],
            ],
            'payment_method_details' => [
                'type' => 'bank_account',
                'bank_account' => [],
                'id' => $paymentMethodId,
            ],
            'redirect_url' => 'https://example.com/callback',
        ];
        $chargeData = array_merge($defaultCharge, $chargeData);

        Http::fake(function ($request) use ($accessToken, $customerId, $paymentMethodId, $chargeData) {
            $url = $request->url();

            // OAuth token endpoint
            if (str_contains($url, 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => $accessToken,
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            // List customers (GET /customers?email=...)
            if (str_contains($url, '/customers') && $request->method() === 'GET') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Customers fetched',
                    'meta' => ['page_info' => ['total' => 0, 'current_page' => 1, 'total_pages' => 0]],
                    'data' => [],
                ], 200);
            }

            // Create customer (POST /customers)
            if (str_contains($url, '/customers') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Customer created',
                    'data' => [
                        'id' => $customerId,
                        'email' => 'test@example.com',
                        'meta' => [],
                        'created_datetime' => '2026-01-01T10:00:00.000Z',
                    ],
                ], 201);
            }

            // Create payment method (POST /payment-methods)
            if (str_contains($url, '/payment-methods') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Payment method created',
                    'data' => [
                        'id' => $paymentMethodId,
                        'type' => 'bank_account',
                        'bank_account' => [],
                        'meta' => [],
                        'created_datetime' => '2026-01-01T10:00:00.000Z',
                    ],
                ], 201);
            }

            // Refund charge (POST /refunds) — v4 endpoint
            if (str_contains($url, '/refunds') && $request->method() === 'POST') {
                $body = json_decode($request->getBody()->getContents(), true);
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_test123',
                        'charge_id' => $body['charge_id'] ?? null,
                        'amount' => $body['amount'] ?? $chargeData['amount'] ?? 5000,
                        'status' => 'completed',
                    ],
                ], 200);
            }

            // Create charge (POST /charges)
            if (str_contains($url, '/charges') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge created',
                    'data' => $chargeData,
                ], 201);
            }

            // Get charge (GET /charges/{id})
            if (str_contains($url, '/charges/') && $request->method() === 'GET') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge fetched',
                    'data' => $chargeData,
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });
    }

    // ── Authentication / Token Tests ──

    public function test_obtains_access_token_from_oauth_endpoint(): void
    {
        $this->mockV4Flow();

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->provider);

        $this->assertEquals('test_access_token', $token);
    }

    public function test_reuses_cached_token_without_refetching(): void
    {
        $tokenCallCount = 0;

        Http::fake(function ($request) use (&$tokenCallCount) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                $tokenCallCount++;
                return Http::response([
                    'access_token' => 'cached_token',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response([
                    'status' => 'success', 'message' => 'Customers fetched',
                    'meta' => ['page_info' => ['total' => 0]],
                    'data' => [],
                ], 200);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Customer created',
                    'data' => ['id' => 'cus_cached', 'email' => 'test@example.com'],
                ], 201);
            }

            if (str_contains($request->url(), '/payment-methods') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Payment method created',
                    'data' => ['id' => 'pmd_cached', 'type' => 'bank_account'],
                ], 201);
            }

            if (str_contains($request->url(), '/charges') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Charge created',
                    'data' => ['id' => 'chg_cached', 'amount' => 5000, 'currency' => 'UGX', 'status' => 'pending'],
                ], 201);
            }

            return Http::response(['status' => 'error'], 404);
        });

        $result1 = $this->provider->initializePayment([
            'amount' => 5000, 'currency' => 'UGX', 'email' => 'test@example.com',
        ]);
        $result2 = $this->provider->initializePayment([
            'amount' => 6000, 'currency' => 'UGX', 'email' => 'test2@example.com',
        ]);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertEquals(1, $tokenCallCount);
    }

    public function test_refreshes_token_after_expiry(): void
    {
        $tokenCallCount = 0;

        Http::fake(function ($request) use (&$tokenCallCount) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                $tokenCallCount++;
                return Http::response([
                    'access_token' => 'token_' . $tokenCallCount,
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response([
                    'status' => 'success', 'message' => 'Customers fetched',
                    'meta' => ['page_info' => ['total' => 0]], 'data' => [],
                ], 200);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Customer created',
                    'data' => ['id' => 'cus_refresh', 'email' => 'test@example.com'],
                ], 201);
            }

            if (str_contains($request->url(), '/payment-methods') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Payment method created',
                    'data' => ['id' => 'pmd_refresh', 'type' => 'bank_account'],
                ], 201);
            }

            if (str_contains($request->url(), '/charges') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success', 'message' => 'Charge created',
                    'data' => ['id' => 'chg_refresh', 'amount' => 5000, 'currency' => 'UGX', 'status' => 'pending'],
                ], 201);
            }

            return Http::response(['status' => 'error'], 404);
        });

        $result1 = $this->provider->initializePayment([
            'amount' => 5000, 'currency' => 'UGX', 'email' => 'test@example.com',
        ]);
        $this->assertTrue($result1['success']);

        // Manually expire the cached token via reflection
        $reflection = new \ReflectionClass($this->provider);
        $tokenProp = $reflection->getProperty('accessTokenExpiresAt');
        $tokenProp->setAccessible(true);
        $tokenProp->setValue($this->provider, time() - 100);

        $result2 = $this->provider->initializePayment([
            'amount' => 6000, 'currency' => 'UGX', 'email' => 'test2@example.com',
        ]);
        $this->assertTrue($result2['success']);

        $this->assertEquals(2, $tokenCallCount);
    }

    public function test_throws_exception_when_token_fetch_fails(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'error' => 'invalid_client',
                    'error_description' => 'Client authentication failed',
                ], 401);
            }
            return Http::response(['status' => 'success'], 200);
        });

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Flutterwave authentication failed');

        $method->invoke($this->provider);
    }

    public function test_throws_exception_when_client_credentials_missing(): void
    {
        Config::set('services.flutterwave.client_id', null);
        Config::set('services.flutterwave.client_secret', null);

        $provider = new FlutterwaveProvider();

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('client credentials not configured');

        $method->invoke($provider);
    }

    // ── Payment Initialization Tests ──

    public function test_initialize_payment_success_with_oauth(): void
    {
        $this->mockV4Flow(
            'oauth_token_1',
            'cus_oauth',
            'pmd_oauth',
            [
                'id' => 'chg_oauth123',
                'amount' => 5000,
                'currency' => 'UGX',
                'reference' => 'BOOKING-ABC123',
                'status' => 'pending',
                'next_action' => [
                    'type' => 'redirect_url',
                    'redirect_url' => [
                        'url' => 'https://developersandbox.flutterwave.com/redirects?card&token=oauth123',
                    ],
                ],
            ]
        );

        $data = [
            'amount' => 5000,
            'currency' => 'UGX',
            'email' => 'test@example.com',
            'customer_name' => 'John Doe',
            'phone' => '+256700000000',
            'reference' => 'BOOKING-ABC123',
            'title' => 'Test Salon',
            'description' => 'Payment for booking',
            'redirect_url' => 'https://example.com/callback',
        ];

        $result = $this->provider->initializePayment($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('chg_oauth123', $result['reference']);
        $this->assertEquals('https://developersandbox.flutterwave.com/redirects?card&token=oauth123', $result['payment_link']);
        $this->assertEquals('chg_oauth123', $result['provider_reference']);
    }

    public function test_initialize_payment_failure(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                ], 200);
            }

            // Customer creation fails
            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'failed',
                    'error' => ['message' => 'Customer email already exists'],
                ], 409);
            }

            return Http::response(['status' => 'success'], 200);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create Flutterwave customer');

        $this->provider->initializePayment([
            'amount' => 5000,
            'currency' => 'UGX',
            'email' => 'test@example.com',
        ]);
    }

    // ── Payment Verification Tests ──

    public function test_verify_payment_success_with_oauth(): void
    {
        $this->mockV4Flow(
            'oauth_token_1',
            'cus_verify',
            'pmd_verify',
            [
                'id' => 'chg_verify123',
                'reference' => 'TX-123456',
                'amount' => 5000,
                'currency' => 'UGX',
                'status' => 'successful',
                'processor_response' => ['type' => 'success', 'code' => '00', 'message' => 'Approved'],
                'created_datetime' => '2026-01-01T10:00:00.000Z',
                'next_action' => [
                    'type' => 'redirect_url',
                    'redirect_url' => ['url' => 'https://example.com/callback'],
                ],
                'payment_method_details' => ['type' => 'bank_account', 'bank_account' => [], 'id' => 'pmd_verify'],
            ]
        );

        $result = $this->provider->verifyPayment('chg_verify123');

        $this->assertTrue($result['success']);
        $this->assertEquals('successful', $result['status']);
        $this->assertEquals('chg_verify123', $result['provider_reference']);
        $this->assertEquals(5000, $result['amount']);
        $this->assertEquals('UGX', $result['currency']);
        $this->assertEquals('bank_account', $result['payment_method']);
    }

    public function test_verify_payment_failed_transaction(): void
    {
        $this->mockV4Flow(
            'oauth_token_1',
            'cus_verify_fail',
            'pmd_verify_fail',
            [
                'id' => 'chg_verify_fail',
                'reference' => 'TX-123456',
                'amount' => 5000,
                'currency' => 'UGX',
                'status' => 'failed',
            ]
        );

        $result = $this->provider->verifyPayment('chg_verify_fail');

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
    }

    public function test_verify_payment_api_error(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                ], 200);
            }

            return Http::response([
                'status' => 'error',
                'error' => ['message' => 'Charge not found'],
            ], 404);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment verification failed');

        $this->provider->verifyPayment('chg_nonexistent');
    }

    // ── Refund Tests ──

    public function test_refund_payment_success_with_oauth(): void
    {
        $refundCalled = false;
        $refundUrl = null;
        $refundPayload = null;

        Http::fake(function ($request) use (&$refundCalled, &$refundUrl, &$refundPayload) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                $refundCalled = true;
                $refundUrl = $request->url();
                $refundPayload = json_decode($request->body(), true);
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_test123',
                        'charge_id' => 'chg_refund123',
                        'amount' => 5000,
                        'status' => 'completed',
                    ],
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $result = $this->provider->refundPayment('chg_refund123', 5000);

        $this->assertTrue($result['success']);
        $this->assertTrue($refundCalled, 'Refund endpoint should have been called');
        $this->assertStringContainsString('/refunds', $refundUrl, 'Should use v4 /refunds endpoint');
        $this->assertNotNull($refundPayload, 'Refund request payload should be captured');
        $this->assertEquals('chg_refund123', $refundPayload['charge_id']);
        $this->assertEquals(5000, $refundPayload['amount']);
        $this->assertEquals('requested_by_customer', $refundPayload['reason']);
        $this->assertEquals('ref_test123', $result['reference']);
        $this->assertEquals(5000, $result['amount_refunded']);
        $this->assertEquals('completed', $result['status']);
    }

    public function test_refund_payment_returns_pending_status(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_pending',
                        'charge_id' => 'chg_pending',
                        'amount' => 5000,
                        'status' => 'pending',
                    ],
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $result = $this->provider->refundPayment('chg_pending', 5000);

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['status']);
    }

    public function test_refund_payment_returns_succeeded_status(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_succeeded',
                        'charge_id' => 'chg_succeeded',
                        'amount' => 5000,
                        'status' => 'succeeded',
                    ],
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $result = $this->provider->refundPayment('chg_succeeded', 5000);

        $this->assertTrue($result['success']);
        $this->assertEquals('succeeded', $result['status']);
    }

    public function test_refund_payment_returns_failed_status(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_failed',
                        'charge_id' => 'chg_failed',
                        'amount' => 5000,
                        'status' => 'failed',
                    ],
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $result = $this->provider->refundPayment('chg_failed', 5000);

        $this->assertTrue($result['success']);
        $this->assertEquals('failed', $result['status']);
    }

    public function test_refund_payment_returns_completed_status(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Charge refunded',
                    'data' => [
                        'id' => 'ref_completed',
                        'charge_id' => 'chg_completed',
                        'amount' => 5000,
                        'status' => 'completed',
                    ],
                ], 200);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $result = $this->provider->refundPayment('chg_completed', 5000);

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);
    }

    public function test_refund_payment_failure(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'idp.flutterwave.com')) {
                return Http::response([
                    'access_token' => 'oauth_token_1',
                    'expires_in' => 600,
                ], 200);
            }

            if (str_contains($request->url(), '/refunds') && $request->method() === 'POST') {
                return Http::response([
                    'status' => 'failed',
                    'error' => ['message' => 'Refund amount exceeds transaction amount'],
                ], 400);
            }

            return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund failed');

        $this->provider->refundPayment('chg_refund_fail', 5000);
    }

    // ── Webhook Signature Tests (unchanged behavior) ──

    public function test_validate_webhook_signature(): void
    {
        $payload = ['event' => 'charge.completed', 'data' => ['id' => 123]];

        $signature = hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $isValid = $this->provider->validateWebhookSignature($payload, $signature);
        $this->assertTrue($isValid);

        $isInvalid = $this->provider->validateWebhookSignature($payload, 'wrong_signature');
        $this->assertFalse($isInvalid);
    }

    public function test_validate_webhook_signature_returns_false_when_secret_missing(): void
    {
        Config::set('services.flutterwave.webhook_secret', null);
        $provider = new FlutterwaveProvider();

        $this->assertFalse($provider->validateWebhookSignature(['event' => 'x'], 'sig'));
    }

    // ── Capabilities ──

    public function test_get_capabilities(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertTrue($capabilities['refunds']);
        $this->assertTrue($capabilities['webhooks']);
        $this->assertTrue($capabilities['tokenized_charging']);
        $this->assertFalse($capabilities['momo_push']);
    }

    // ── Provider Name ──

    public function test_get_provider_name(): void
    {
        $this->assertEquals('flutterwave', $this->provider->getProviderName());
    }
}
