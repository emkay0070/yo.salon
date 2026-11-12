<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = config('services.flutterwave.client_id');
$clientSecret = config('services.flutterwave.client_secret');

$tokenResponse = Http::asForm()->post('https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token', [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'grant_type' => 'client_credentials',
]);
$accessToken = $tokenResponse->json()['access_token'];

$headers = [
    'Authorization' => 'Bearer ' . $accessToken,
    'Content-Type' => 'application/json',
    'X-Idempotency-Key' => str_replace('-', '', uniqid('', true)),
];

// Step 1: Create customer
echo "Step 1: Create customer\n";
$customerResponse = Http::withHeaders($headers)->post('https://developersandbox-api.flutterwave.com/customers', [
    'email' => 'test@example.com',
]);
echo "  Status: " . $customerResponse->status() . "\n";
$customerData = $customerResponse->json()['data'] ?? null;
$customerId = $customerData['id'] ?? null;
echo "  Customer ID: $customerId\n\n";

// Step 2: Create payment method (mobile money)
echo "Step 2: Create payment method\n";
$headers['X-Idempotency-Key'] = str_replace('-', '', uniqid('', true));
$pmPayloads = [
    ['customer_id' => $customerId, 'type' => 'mobile_money_uganda', 'details' => ['phone_number' => '256700000000']],
    ['customer_id' => $customerId, 'type' => 'mobile_money_uganda', 'details' => ['phoneNumber' => '256700000000']],
    ['customer_id' => $customerId, 'type' => 'mobile_money', 'details' => ['phone_number' => '256700000000']],
];

$paymentMethodId = null;
foreach ($pmPayloads as $i => $payload) {
    echo "  Attempt " . ($i + 1) . ": " . json_encode($payload) . "\n";
    $pmResponse = Http::withHeaders($headers)->post('https://developersandbox-api.flutterwave.com/payment-methods', $payload);
    echo "    Status: " . $pmResponse->status() . " | Body: " . $pmResponse->body() . "\n";
    if ($pmResponse->successful()) {
        $pmData = $pmResponse->json()['data'] ?? null;
        $paymentMethodId = $pmData['id'] ?? null;
        echo "    >>> Payment Method ID: $paymentMethodId\n";
        break;
    }
    echo "\n";
}

// Step 3: Create charge
if ($customerId && $paymentMethodId) {
    echo "\nStep 3: Create charge\n";
    $headers['X-Idempotency-Key'] = str_replace('-', '', uniqid('', true));
    $chargeResponse = Http::withHeaders($headers)->post('https://developersandbox-api.flutterwave.com/charges', [
        'amount' => 5000,
        'currency' => 'UGX',
        'reference' => 'BOOKING-' . uniqid(),
        'customer_id' => $customerId,
        'payment_method_id' => $paymentMethodId,
        'redirect_url' => 'https://example.com/callback',
        'recurring' => false,
    ]);
    echo "  Status: " . $chargeResponse->status() . "\n";
    echo "  Body: " . $chargeResponse->body() . "\n";
    
    if ($chargeResponse->successful()) {
        $data = $chargeResponse->json()['data'] ?? null;
        $chargeId = $data['id'] ?? null;
        $nextAction = $data['next_action'] ?? null;
        $redirectUrl = $nextAction['redirect_url']['url'] ?? null;
        echo "  >>> Charge ID: $chargeId\n";
        echo "  >>> Redirect URL: " . ($redirectUrl ? substr($redirectUrl, 0, 80) . '...' : 'N/A') . "\n";
        echo "  >>> Status: " . ($data['status'] ?? 'N/A') . "\n";
        
        // Step 4: Verify charge
        if ($chargeId) {
            echo "\nStep 4: Verify charge\n";
            $headers['X-Idempotency-Key'] = str_replace('-', '', uniqid('', true));
            $verifyResponse = Http::withHeaders($headers)->get("https://developersandbox-api.flutterwave.com/charges/$chargeId");
            echo "  Status: " . $verifyResponse->status() . "\n";
            echo "  Body: " . $verifyResponse->body() . "\n";
        }
    }
}
