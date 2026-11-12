<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$locationId = 'ba489e65-5e63-44b8-99d3-cfaa1bc11ec4';

echo "=== Testing API Contract ===\n\n";

// Simulate request with no location context (All customers)
echo "Test 1: No location context (All customers)\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';
$_GET = [];

$request = \Illuminate\Http\Request::create('/api/v1/customers', 'GET');
$request->headers->set('X-Provider-Id', $providerId);
$request->attributes->set('provider_id', $providerId);
$request->attributes->set('location_id', null);
$request->attributes->set('location_scope', 'all');

$controller = new \App\Http\Controllers\Api\V1\CustomerController();
$response = $controller->index($request);

$data = json_decode($response->getContent(), true);
echo "  Customers returned: " . count($data['customers']) . "\n";
echo "  Expected: 3\n";
echo "  Result: " . (count($data['customers']) === 3 ? "PASS" : "FAIL") . "\n\n";

// Simulate request with explicit location_id query param
echo "Test 2: Explicit location_id query param (middleware context)\n";
$_GET = ['location_id' => $locationId];

$request = \Illuminate\Http\Request::create('/api/v1/customers', 'GET', $_GET);
$request->headers->set('X-Provider-Id', $providerId);
$request->attributes->set('provider_id', $providerId);
// Don't set middleware location context - let controller use query param

$controller = new \App\Http\Controllers\Api\V1\CustomerController();
$response = $controller->index($request);

echo "  Response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 200) {
    $data = json_decode($response->getContent(), true);
    echo "  Customers returned: " . count($data['customers']) . "\n";
    echo "  Expected: 0 (no bookings at Emo Spa)\n";
    echo "  Result: " . (count($data['customers']) === 0 ? "PASS" : "FAIL") . "\n\n";
} else {
    echo "  Response: " . $response->getContent() . "\n";
    echo "  Expected: 200 with 0 customers\n";
    echo "  Result: FAIL (validation working correctly)\n\n";
}

// Simulate request with location_filter=unassigned
echo "Test 3: location_filter=unassigned\n";
$_GET = ['location_filter' => 'unassigned'];

$request = \Illuminate\Http\Request::create('/api/v1/customers', 'GET', $_GET);
$request->headers->set('X-Provider-Id', $providerId);
$request->attributes->set('provider_id', $providerId);
// Don't set middleware location context

$controller = new \App\Http\Controllers\Api\V1\CustomerController();
$response = $controller->index($request);

echo "  Response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 200) {
    $data = json_decode($response->getContent(), true);
    echo "  Customers returned: " . count($data['customers']) . "\n";
    echo "  Expected: 2 (Emmak, lome)\n";
    echo "  Result: " . (count($data['customers']) === 2 ? "PASS" : "FAIL") . "\n\n";
} else {
    echo "  Response: " . $response->getContent() . "\n";
    echo "  Expected: 200 with 2 customers\n";
    echo "  Result: FAIL\n\n";
}

// Test that explicit location_filter overrides middleware context
echo "Test 4: location_filter=unassigned overrides middleware location context\n";
$_GET = ['location_filter' => 'unassigned'];

$request = \Illuminate\Http\Request::create('/api/v1/customers', 'GET', $_GET);
$request->headers->set('X-Provider-Id', $providerId);
$request->attributes->set('provider_id', $providerId);
$request->attributes->set('location_id', $locationId); // Middleware sets this
$request->attributes->set('location_scope', 'specific');

$controller = new \App\Http\Controllers\Api\V1\CustomerController();
$response = $controller->index($request);

echo "  Response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 200) {
    $data = json_decode($response->getContent(), true);
    echo "  Customers returned: " . count($data['customers']) . "\n";
    echo "  Expected: 2 (unassigned filter overrides middleware context)\n";
    echo "  Result: " . (count($data['customers']) === 2 ? "PASS" : "FAIL") . "\n\n";
} else {
    echo "  Response: " . $response->getContent() . "\n";
    echo "  Expected: 200 with 2 customers\n";
    echo "  Result: FAIL\n\n";
}

echo "=== Summary ===\n";
echo "All tests verify that explicit query params take precedence over middleware context.\n";
