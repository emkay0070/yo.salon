<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Checking Owner's Clients API Response ===\n";
echo "Provider ID: {$providerId}\n\n";

// Simulate what CustomerController::index returns
echo "Step 1: What CustomerController::index would return (customer_providers relationship)\n";

$customers = \App\Models\Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    })
    ->with('bookings')
    ->get();

echo "Customers with customer_providers relationship: {$customers->count()}\n";
foreach ($customers as $customer) {
    echo "  - {$customer->name} ({$customer->phone})\n";
}

echo "\nStep 2: Compare with customers who have bookings\n";

$customersWithBookings = \DB::table('bookings')
    ->where('provider_id', $providerId)
    ->distinct()
    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
    ->get(['customers.id', 'customers.name', 'customers.phone']);

echo "Customers with bookings: {$customersWithBookings->count()}\n";

$missingRelationships = 0;
foreach ($customersWithBookings as $bookingCustomer) {
    $hasRelationship = \DB::table('customer_providers')
        ->where('customer_id', $bookingCustomer->id)
        ->where('provider_id', $providerId)
        ->exists();

    $status = $hasRelationship ? '✓' : '✗';
    echo "  {$status} {$bookingCustomer->name} - customer_providers: " . ($hasRelationship ? 'YES' : 'NO') . "\n";

    if (!$hasRelationship) {
        $missingRelationships++;
    }
}

echo "\nStep 3: Summary\n";
echo "Total customers with bookings: {$customersWithBookings->count()}\n";
echo "Customers with customer_providers: {$customers->count()}\n";
echo "Missing customer_providers: {$missingRelationships}\n";
echo "\n";
echo "CONCLUSION: The owner's Clients page shows {$customers->count()} customers instead of {$customersWithBookings->count()}\n";
echo "because the API filters by customer_providers relationship.\n";
echo "The {$missingRelationships} missing relationships are causing the discrepancy.\n";
