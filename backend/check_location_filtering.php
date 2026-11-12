<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$locationId = 'ba489e65-5e63-44b8-99d3-cfaa1bc11ec4';

echo "=== Checking Location Filtering Issue ===\n";
echo "Provider ID: {$providerId}\n";
echo "Location ID: {$locationId}\n\n";

// Get the 3 customers with customer_providers
$customers = \App\Models\Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    })
    ->get();

echo "Customers with customer_providers: {$customers->count()}\n\n";

foreach ($customers as $customer) {
    echo "Customer: {$customer->name}\n";
    echo "  ID: {$customer->id}\n";

    // Check if customer has bookings at this location
    $bookingsAtLocation = \DB::table('bookings')
        ->where('customer_id', $customer->id)
        ->where('location_id', $locationId)
        ->count();

    echo "  Bookings at location {$locationId}: {$bookingsAtLocation}\n";

    // Check total bookings
    $totalBookings = \DB::table('bookings')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $providerId)
        ->count();

    echo "  Total bookings at provider: {$totalBookings}\n";

    if ($bookingsAtLocation === 0 && $totalBookings > 0) {
        echo "  ⚠️  This customer has bookings at the provider but NOT at this specific location\n";
        echo "  ⚠️  When location_scope='specific', this customer will be filtered out!\n";
    }

    echo "\n";
}

echo "CONCLUSION:\n";
echo "When location_scope='specific', the controller filters customers to only those\n";
echo "with bookings at the specific location. If the 3 customers have bookings at\n";
echo "other locations but not this one, they will be filtered out and the API returns empty.\n";
