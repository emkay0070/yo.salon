<?php

/**
 * Dry-run script to identify missing customer-provider relationships
 * 
 * This script analyzes existing bookings and identifies which customer-provider
 * relationships are missing from the customer_providers table.
 * 
 * Usage: php dry_run_missing_relationships.php
 * 
 * Output: JSON report of missing relationships that can be safely inferred
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;

echo "=== Dry-run: Missing Customer-Provider Relationships ===\n\n";

// Get all bookings
$bookings = Booking::all();

echo "Total bookings in database: " . $bookings->count() . "\n\n";

// Track missing relationships
$missingRelationships = [];
$ambiguousCases = [];

foreach ($bookings as $booking) {
    $customerId = $booking->customer_id;
    $providerId = $booking->provider_id;
    
    // Check if relationship exists
    $exists = DB::table('customer_providers')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->exists();
    
    if (!$exists) {
        // Verify customer and provider still exist
        $customer = Customer::find($customerId);
        $provider = Provider::find($providerId);
        
        if (!$customer || !$provider) {
            // Orphaned booking - customer or provider deleted
            $ambiguousCases[] = [
                'type' => 'orphaned_booking',
                'booking_id' => $booking->id,
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'reason' => $customer ? 'Provider deleted' : 'Customer deleted',
            ];
            continue;
        }
        
        // Safe to infer: booking exists with both customer and provider
        $missingRelationships[] = [
            'booking_id' => $booking->id,
            'customer_id' => $customerId,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'provider_id' => $providerId,
            'provider_name' => $provider->name,
            'booking_date' => $booking->date,
            'booking_status' => $booking->status,
        ];
    }
}

echo "Missing relationships found: " . count($missingRelationships) . "\n";
echo "Ambiguous cases (orphaned records): " . count($ambiguousCases) . "\n\n";

// Group by provider for easier review
$byProvider = [];
foreach ($missingRelationships as $rel) {
    $providerId = $rel['provider_id'];
    if (!isset($byProvider[$providerId])) {
        $byProvider[$providerId] = [
            'provider_id' => $providerId,
            'provider_name' => $rel['provider_name'],
            'missing_count' => 0,
            'relationships' => [],
        ];
    }
    $byProvider[$providerId]['missing_count']++;
    $byProvider[$providerId]['relationships'][] = $rel;
}

echo "=== Summary by Provider ===\n";
foreach ($byProvider as $provider) {
    echo "Provider: {$provider['provider_name']} ({$provider['provider_id']})\n";
    echo "  Missing relationships: {$provider['missing_count']}\n\n";
}

// Output full report as JSON
$report = [
    'generated_at' => now()->toIso8601String(),
    'total_bookings' => $bookings->count(),
    'missing_relationships_count' => count($missingRelationships),
    'ambiguous_cases_count' => count($ambiguousCases),
    'by_provider' => $byProvider,
    'all_missing_relationships' => $missingRelationships,
    'ambiguous_cases' => $ambiguousCases,
];

$reportPath = __DIR__ . '/missing_relationships_report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

echo "Full report saved to: $reportPath\n\n";

echo "=== Sample of Missing Relationships (first 10) ===\n";
foreach (array_slice($missingRelationships, 0, 10) as $rel) {
    echo "Booking: {$rel['booking_id']}\n";
    echo "  Customer: {$rel['customer_name']} ({$rel['customer_id']})\n";
    echo "  Provider: {$rel['provider_name']} ({$rel['provider_id']})\n";
    echo "  Date: {$rel['booking_date']}\n";
    echo "  Status: {$rel['booking_status']}\n\n";
}

if (count($ambiguousCases) > 0) {
    echo "=== Ambiguous Cases ===\n";
    foreach ($ambiguousCases as $case) {
        echo "Booking: {$case['booking_id']}\n";
        echo "  Type: {$case['type']}\n";
        echo "  Reason: {$case['reason']}\n\n";
    }
}

echo "=== End of Dry-run ===\n";
