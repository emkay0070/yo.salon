<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$specialistId = '01a0e836-3a6a-735a-a769-5be031d25a04';

echo "=== Checking Clients Page Data ===\n";
echo "Provider ID: {$providerId}\n";
echo "Specialist ID: {$specialistId}\n\n";

// Get the specialist's customers via customer_specialist relationship
echo "Step 1: What the Clients API would return (customer_specialist relationship)\n";

$specialist = \App\Models\Specialist::find($specialistId);
if ($specialist) {
    $customers = $specialist->customers()
        ->wherePivot('provider_id', $providerId)
        ->withPivot('is_favorite', 'is_following', 'total_bookings', 'total_spent', 'last_interaction_at')
        ->get();

    echo "Customers via specialist->customers(): {$customers->count()}\n";
    foreach ($customers as $customer) {
        echo "  - {$customer->name} ({$customer->phone}) - Bookings: {$customer->pivot->total_bookings}\n";
    }
} else {
    echo "Specialist not found\n";
}

echo "\nStep 2: What the schedule shows (bookings with customer data)\n";

$bookingsToday = \DB::table('bookings')
    ->where('provider_id', $providerId)
    ->where('specialist_id', $specialistId)
    ->where('date', '2026-10-09')
    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
    ->get(['customers.name', 'customers.phone', 'bookings.time', 'bookings.status']);

echo "Bookings today: {$bookingsToday->count()}\n";
foreach ($bookingsToday as $booking) {
    echo "  - {$booking->name} ({$booking->phone}) - {$booking->time} - {$booking->status}\n";
}

echo "\nStep 3: Check customer_specialist for today's booking customers\n";

$todayCustomerIds = $bookingsToday->pluck('name')->unique();
echo "Unique customers in today's bookings: {$todayCustomerIds->count()}\n";

foreach ($todayCustomerIds as $customerName) {
    $customer = \DB::table('customers')->where('name', $customerName)->first();
    if ($customer) {
        $hasRelationship = \DB::table('customer_specialist')
            ->where('customer_id', $customer->id)
            ->where('specialist_id', $specialistId)
            ->where('provider_id', $providerId)
            ->exists();

        $status = $hasRelationship ? '✓' : '✗';
        echo "  {$status} {$customer->name} - customer_specialist relationship: " . ($hasRelationship ? 'YES' : 'NO') . "\n";
    }
}

echo "\nStep 4: Check if the specialist has an active assignment\n";

$assignment = \DB::table('specialist_assignments')
    ->where('specialist_id', $specialistId)
    ->where('provider_id', $providerId)
    ->whereIn('status', ['ACTIVE', 'active'])
    ->first();

if ($assignment) {
    echo "✓ Specialist has active assignment\n";
    echo "  Location ID: " . ($assignment->location_id ?? 'NULL (provider-wide)') . "\n";
    echo "  Status: {$assignment->status}\n";
} else {
    echo "✗ Specialist has NO active assignment\n";
}

echo "\nStep 5: Check provider slug for API request\n";

$provider = \DB::table('providers')->where('id', $providerId)->first();
if ($provider) {
    echo "Provider slug: {$provider->slug}\n";
    echo "Expected API call: GET /v1/specialist-portal/customers?provider_slug={$provider->slug}\n";
} else {
    echo "Provider not found\n";
}
