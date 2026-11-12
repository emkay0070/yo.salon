<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Checking Specialist-Customer Relationships ===\n";
echo "Provider ID: {$providerId}\n\n";

// Get specialists for this provider
$specialists = \DB::table('specialist_assignments')
    ->where('provider_id', $providerId)
    ->whereIn('status', ['ACTIVE', 'active'])
    ->distinct()
    ->pluck('specialist_id');

echo "Active specialists for provider: {$specialists->count()}\n\n";

foreach ($specialists as $specialistId) {
    echo "Specialist ID: {$specialistId}\n";

    // Get customer_specialist relationships for this specialist at this provider
    $customerSpecialistCount = \DB::table('customer_specialist')
        ->where('specialist_id', $specialistId)
        ->where('provider_id', $providerId)
        ->count();

    echo "  customer_specialist relationships: {$customerSpecialistCount}\n";

    // Get customers with bookings for this provider
    $customersWithBookings = \DB::table('bookings')
        ->where('provider_id', $providerId)
        ->where('specialist_id', $specialistId)
        ->distinct()
        ->pluck('customer_id');

    echo "  Customers with bookings for this specialist: {$customersWithBookings->count()}\n";

    // Check which of these have customer_specialist relationships
    $missingRelationships = 0;
    foreach ($customersWithBookings as $customerId) {
        $hasRelationship = \DB::table('customer_specialist')
            ->where('customer_id', $customerId)
            ->where('specialist_id', $specialistId)
            ->where('provider_id', $providerId)
            ->exists();

        if (!$hasRelationship) {
            $missingRelationships++;
        }
    }

    echo "  Missing customer_specialist relationships: {$missingRelationships}\n";
    echo "\n";
}

// Also check the 3 customers that have customer_providers
echo "=== Checking customer_specialist for the 3 customers with customer_providers ===\n";

$customerIdsWithProviders = [
    '01a11f1a-4795-71ea-b1b9-f7706f9c5bc1',
    '01a11f22-de30-7009-9afc-398cb8d3498b',
    '01a11f4e-cf1b-715e-9bd8-a0cec8d61996',
];

foreach ($customerIdsWithProviders as $customerId) {
    echo "Customer ID: {$customerId}\n";

    $customerSpecialistCount = \DB::table('customer_specialist')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->count();

    echo "  customer_specialist relationships: {$customerSpecialistCount}\n";

    if ($customerSpecialistCount > 0) {
        $relationships = \DB::table('customer_specialist')
            ->where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->get(['specialist_id', 'total_bookings', 'last_interaction_at']);

        foreach ($relationships as $rel) {
            echo "    - Specialist ID: {$rel->specialist_id}, Bookings: {$rel->total_bookings}, Last: {$rel->last_interaction_at}\n";
        }
    }
    echo "\n";
}
