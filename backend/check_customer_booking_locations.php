<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$locationId = 'ba489e65-5e63-44b8-99d3-cfaa1bc11ec4';

echo "=== Checking Customer Booking Locations ===\n";
echo "Provider ID: {$providerId}\n";
echo "Current Location ID: {$locationId}\n\n";

// Get all locations for this provider
$locations = \DB::table('locations')
    ->where('provider_id', $providerId)
    ->get();

echo "Provider locations: {$locations->count()}\n";
foreach ($locations as $loc) {
    echo "  - {$loc->id}: {$loc->name}\n";
}

echo "\n";

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

    // Get all bookings for this customer at this provider
    $bookings = \DB::table('bookings')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $providerId)
        ->get();

    echo "  Total bookings at provider: {$bookings->count()}\n";

    // Group bookings by location
    $bookingsByLocation = [];
    foreach ($bookings as $booking) {
        $locId = $booking->location_id ?? 'NULL';
        if (!isset($bookingsByLocation[$locId])) {
            $bookingsByLocation[$locId] = 0;
        }
        $bookingsByLocation[$locId]++;
    }

    echo "  Bookings by location:\n";
    foreach ($bookingsByLocation as $locId => $count) {
        $locationName = $locId === 'NULL' ? 'No location' : ($locations->where('id', $locId)->first()->name ?? 'Unknown');
        echo "    - {$locationName} ({$locId}): {$count} bookings\n";
    }

    echo "\n";
}

echo "CONCLUSION:\n";
echo "The 3 customers have bookings at the provider, but we need to see if they\n";
echo "have bookings at the specific location vs other locations.\n";
