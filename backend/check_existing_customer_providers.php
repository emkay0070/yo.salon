<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Existing customer_providers Relationships ===\n";

// Get the provider from our previous investigation
$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "Provider ID: {$providerId}\n\n";

// Count all customer_providers for this provider
$cpCount = \DB::table('customer_providers')
    ->where('provider_id', $providerId)
    ->count();

echo "Total customer_providers for provider: {$cpCount}\n";

if ($cpCount > 0) {
    $cpRecords = \DB::table('customer_providers')
        ->where('provider_id', $providerId)
        ->get();

    echo "\nExisting customer_providers records:\n";
    foreach ($cpRecords as $record) {
        echo "  - Customer ID: {$record->customer_id}, Provider ID: {$record->provider_id}, Visits: {$record->visits}, Joined: {$record->joined_at}\n";
    }

    // Check if these customers still exist in customers table
    echo "\nVerifying customers exist in customers table:\n";
    foreach ($cpRecords as $record) {
        $customer = \DB::table('customers')
            ->where('id', $record->customer_id)
            ->first();

        if ($customer) {
            echo "  ✓ Customer {$record->customer_id} exists: {$customer->name} ({$customer->phone})\n";
        } else {
            echo "  ✗ Customer {$record->customer_id} NOT FOUND in customers table\n";
        }
    }
} else {
    echo "No customer_providers records found for this provider.\n";
}

// Check all customers that have bookings for this provider
echo "\n=== Checking Customers with Bookings for this Provider ===\n";

$customersWithBookings = \DB::table('bookings')
    ->where('provider_id', $providerId)
    ->distinct()
    ->pluck('customer_id');

echo "Customers with bookings: {$customersWithBookings->count()}\n";

foreach ($customersWithBookings as $customerId) {
    $hasCustomerProvider = \DB::table('customer_providers')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->exists();

    $customer = \DB::table('customers')
        ->where('id', $customerId)
        ->first();

    $status = $hasCustomerProvider ? '✓' : '✗';
    $customerInfo = $customer ? "{$customer->name} ({$customer->phone})" : 'NOT FOUND';
    echo "  {$status} Customer {$customerId}: {$customerInfo}\n";
}
