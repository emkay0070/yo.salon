<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== All Customer_Providers for provider 01a0e836-39fa-700e-991d-797669bb5a32 ===\n";
$cp = \DB::table('customer_providers')
    ->where('provider_id', '01a0e836-39fa-700e-991d-797669bb5a32')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "Found {$cp->count()} customer_providers\n";
foreach ($cp as $row) {
    echo "Customer: {$row->customer_id}, Provider: {$row->provider_id}, Visits: {$row->visits}, Created: {$row->created_at}\n";
}

echo "\n=== Checking specific customers from bookings ===\n";
$customerIds = ['01a11cfd-bba2-704f-aaa7-9bff13b826fa', '01a11cff-a309-70ff-9307-488e6ea0a8ad'];
foreach ($customerIds as $customerId) {
    $exists = \DB::table('customer_providers')
        ->where('customer_id', $customerId)
        ->where('provider_id', '01a0e836-39fa-700e-991d-797669bb5a32')
        ->exists();
    echo "Customer {$customerId}: " . ($exists ? 'EXISTS' : 'NOT FOUND') . " in customer_providers\n";
}
