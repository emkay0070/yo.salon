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
        
        Config::set('services.flutterwave.secret_key', 'test_secret');
        Config::set('services.flutterwave.public_key', 'test_public');
        Config::set('services.flutterwave.webhook_secret', 'test_webhook_secret');
        
        $this->provider = new FlutterwaveProvider();
    }

    public function test_initialize_payment_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link',
                'data' => [
                    'tx_ref' => 'TX-123456',
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/123456'
                ]
            ], 200)
        ]);

        $data = [
            'amount' => 5000,
            'currency' => 'UGX',
            'email' => 'test@example.com'
        ];

        $result = $this->provider->initializePayment($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('TX-123456', $result['reference']);
        $this->assertEquals('https://checkout.flutterwave.com/v3/hosted/pay/123456', $result['payment_link']);
    }

    public function test_verify_payment_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'message' => 'Transaction fetched successfully',
                'data' => [
                    'id' => 123456,
                    'tx_ref' => 'TX-123456',
                    'flw_ref' => 'FLW-123456',
                    'status' => 'successful',
                    'amount' => 5000,
                    'currency' => 'UGX',
                    'app_fee' => 140,
                    'payment_type' => 'card',
                    'customer' => [
                        'name' => 'John Doe',
                        'email' => 'test@example.com'
                    ]
                ]
            ], 200)
        ]);

        $result = $this->provider->verifyPayment('TX-123456');

        $this->assertTrue($result['success']);
        $this->assertEquals('successful', $result['status']);
        $this->assertEquals('FLW-123456', $result['provider_reference']);
        $this->assertEquals(140, $result['fees']);
        $this->assertEquals('card', $result['payment_method']);
    }

    public function test_verify_payment_failed_transaction(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success', // HTTP level success
                'message' => 'Transaction fetched successfully',
                'data' => [
                    'id' => 123456,
                    'tx_ref' => 'TX-123456',
                    'flw_ref' => 'FLW-123456',
                    'status' => 'failed', // Transaction level failure
                    'amount' => 5000,
                    'currency' => 'UGX'
                ]
            ], 200)
        ]);

        $result = $this->provider->verifyPayment('TX-123456');

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
    }

    public function test_validate_webhook_signature(): void
    {
        $payload = ['event' => 'charge.completed', 'data' => ['id' => 123]];
        
        // Calculate HMAC SHA256 signature
        $signature = hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');
        
        $isValid = $this->provider->validateWebhookSignature($payload, $signature);
        $this->assertTrue($isValid);
        
        $isInvalid = $this->provider->validateWebhookSignature($payload, 'wrong_signature');
        $this->assertFalse($isInvalid);
    }
    
    public function test_get_capabilities(): void
    {
        $capabilities = $this->provider->getCapabilities();
        
        $this->assertIsArray($capabilities);
        $this->assertTrue($capabilities['refunds']);
        $this->assertTrue($capabilities['webhooks']);
    }
}
