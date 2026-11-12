<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$payload = [
    'provider_id' => '01a0e836-39fa-700e-991d-797669bb5a32',
    'location_id' => 'ba489e65-5e63-44b8-99d3-cfaa1bc11ec4',
    'service_id' => ['01a0f7b8-9f70-7305-b47c-b3377ad96fbd'],
    'customer_name' => 'Walk-in Guest',
    'customer_phone' => '0000000000',
    'date' => date('Y-m-d'),
    'time' => date('H:i'),
    'status' => 'pending',
    'internal_booking' => true
];

$request = Illuminate\Http\Request::create('/api/v1/bookings', 'POST', $payload);
$request->headers->set('Accept', 'application/json');

// Mock sanctum user (staff/provider)
$user = \App\Models\User::first();
$app['auth']->guard('sanctum')->setUser($user);
$request->attributes->set('provider_id', '01a0e836-39fa-700e-991d-797669bb5a32');
$request->attributes->set('location_id', 'ba489e65-5e63-44b8-99d3-cfaa1bc11ec4');

$response = $kernel->handle($request);

echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . $response->getContent() . "\n";
