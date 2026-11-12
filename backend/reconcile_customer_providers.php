<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Historical Customer-Provider Reconciliation ===\n";
echo "Provider ID: {$providerId}\n\n";

// Step 1: Identify eligible customers
echo "Step 1: Identifying eligible customers...\n";

$customersWithBookings = \DB::table('bookings')
    ->where('provider_id', $providerId)
    ->distinct()
    ->pluck('customer_id');

echo "Total customers with bookings for this provider: {$customersWithBookings->count()}\n\n";

$eligibleCustomers = [];
$skippedCustomers = [];

foreach ($customersWithBookings as $customerId) {
    // Check if customer exists
    $customer = \DB::table('customers')->where('id', $customerId)->first();
    if (!$customer) {
        $skippedCustomers[] = [
            'customer_id' => $customerId,
            'reason' => 'Customer does not exist in customers table'
        ];
        continue;
    }

    // Check if customer already has customer_providers relationship
    $hasRelationship = \DB::table('customer_providers')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->exists();

    if ($hasRelationship) {
        $skippedCustomers[] = [
            'customer_id' => $customerId,
            'reason' => 'Relationship already exists'
        ];
        continue;
    }

    // Verify booking belongs to this provider
    $bookingCount = \DB::table('bookings')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->count();

    if ($bookingCount === 0) {
        $skippedCustomers[] = [
            'customer_id' => $customerId,
            'reason' => 'No bookings found for this provider'
        ];
        continue;
    }

    // Collect booking details for verification
    $bookings = \DB::table('bookings')
        ->where('customer_id', $customerId)
        ->where('provider_id', $providerId)
        ->get(['id', 'status', 'date', 'location_id']);

    $eligibleCustomers[] = [
        'customer_id' => $customerId,
        'booking_count' => $bookingCount,
        'bookings' => $bookings,
    ];
}

echo "Eligible customers for relationship creation: " . count($eligibleCustomers) . "\n";
echo "Skipped customers: " . count($skippedCustomers) . "\n\n";

// Step 2: Verify relationship integrity
echo "Step 2: Verifying relationship integrity...\n";

$verifiedCustomers = [];
$ambiguousCustomers = [];

foreach ($eligibleCustomers as $customer) {
    $customerId = $customer['customer_id'];
    $issues = [];

    // Check if customer has bookings with OTHER providers
    $otherProviderBookings = \DB::table('bookings')
        ->where('customer_id', $customerId)
        ->where('provider_id', '!=', $providerId)
        ->count();

    if ($otherProviderBookings > 0) {
        $issues[] = "Customer has {$otherProviderBookings} bookings with other providers";
    }

    // Check if customer has relationships with OTHER providers
    $otherProviderRelationships = \DB::table('customer_providers')
        ->where('customer_id', $customerId)
        ->where('provider_id', '!=', $providerId)
        ->count();

    if ($otherProviderRelationships > 0) {
        $issues[] = "Customer has {$otherProviderRelationships} relationships with other providers";
    }

    // Check booking status distribution
    $statusCounts = [];
    foreach ($customer['bookings'] as $booking) {
        $status = $booking->status;
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    if (count($issues) > 0) {
        $ambiguousCustomers[] = [
            'customer_id' => $customerId,
            'issues' => $issues,
            'booking_status_counts' => $statusCounts,
        ];
    } else {
        $verifiedCustomers[] = [
            'customer_id' => $customerId,
            'booking_count' => $customer['booking_count'],
            'booking_status_counts' => $statusCounts,
        ];
    }
}

echo "Verified customers: " . count($verifiedCustomers) . "\n";
echo "Ambiguous customers requiring review: " . count($ambiguousCustomers) . "\n\n";

// Step 3: Check schema constraints
echo "Step 3: Checking schema constraints...\n";

// Get customer_providers table structure (PostgreSQL)
$columns = \DB::select("
    SELECT column_name, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_name = 'customer_providers'
    ORDER BY ordinal_position
");

$requiredFields = [];
$nullableFields = [];

foreach ($columns as $column) {
    if ($column->is_nullable === 'NO' && $column->column_default === null) {
        $requiredFields[] = $column->column_name;
    } else {
        $nullableFields[] = $column->column_name;
    }
}

echo "Required fields: " . implode(', ', $requiredFields) . "\n";
echo "Nullable fields: " . implode(', ', $nullableFields) . "\n";

// Check for unique constraints (PostgreSQL)
$constraints = \DB::select("
    SELECT
        tc.constraint_name,
        kcu.column_name
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
        ON tc.constraint_name = kcu.constraint_name
    WHERE tc.table_name = 'customer_providers'
        AND tc.constraint_type = 'UNIQUE'
    ORDER BY tc.constraint_name, kcu.ordinal_position
");

$uniqueConstraints = [];
foreach ($constraints as $constraint) {
    if (!isset($uniqueConstraints[$constraint->constraint_name])) {
        $uniqueConstraints[$constraint->constraint_name] = [];
    }
    $uniqueConstraints[$constraint->constraint_name][] = $constraint->column_name;
}

echo "\nUnique constraints:\n";
foreach ($uniqueConstraints as $name => $columns) {
    echo "  - {$name}: " . implode(', ', $columns) . "\n";
}

echo "\n";

// Step 4: Review addSalonRelationship
echo "Step 4: Reviewing addSalonRelationship for idempotent safety...\n";

$serviceCode = file_get_contents(__DIR__.'/app/Services/CustomerService.php');
$addSalonMethodStart = strpos($serviceCode, 'public function addSalonRelationship');
$addSalonMethodEnd = strpos($serviceCode, 'public function incrementVisit', $addSalonMethodStart);
$methodCode = substr($serviceCode, $addSalonMethodStart, $addSalonMethodEnd - $addSalonMethodStart);

echo "Method signature and implementation:\n";
echo $methodCode . "\n";

// Check for ON CONFLICT clause
$hasConflictClause = strpos($methodCode, 'ON CONFLICT') !== false;
$hasExistsCheck = strpos($methodCode, 'exists()') !== false;

echo "Has existence check: " . ($hasExistsCheck ? 'YES' : 'NO') . "\n";
echo "Has ON CONFLICT clause: " . ($hasConflictClause ? 'YES' : 'NO') . "\n";
echo "Idempotent: " . (($hasExistsCheck || $hasConflictClause) ? 'YES' : 'NO') . "\n\n";

// Step 5: Generate report
echo "Step 5: Generating reconciliation report...\n\n";

echo "=== RECONCILIATION REPORT ===\n";
echo "Provider ID: {$providerId}\n";
echo "Report Date: " . date('Y-m-d H:i:s') . "\n\n";

echo "SUMMARY\n";
echo "-------\n";
echo "Total customers with bookings: " . count($customersWithBookings) . "\n";
echo "Eligible for relationship creation: " . count($eligibleCustomers) . "\n";
echo "Verified safe to create: " . count($verifiedCustomers) . "\n";
echo "Ambiguous (requires review): " . count($ambiguousCustomers) . "\n";
echo "Skipped (already exists or invalid): " . count($skippedCustomers) . "\n";
echo "Relationships to be created: " . count($verifiedCustomers) . "\n\n";

echo "VERIFIED CUSTOMERS (Safe to Create)\n";
echo "----------------------------------\n";
foreach ($verifiedCustomers as $customer) {
    echo "Customer ID: {$customer['customer_id']}\n";
    echo "  Booking count: {$customer['booking_count']}\n";
    echo "  Status breakdown: " . json_encode($customer['booking_status_counts']) . "\n";
    echo "\n";
}

if (count($ambiguousCustomers) > 0) {
    echo "AMBIGUOUS CUSTOMERS (Requires Review)\n";
    echo "-------------------------------------\n";
    foreach ($ambiguousCustomers as $customer) {
        echo "Customer ID: {$customer['customer_id']}\n";
        echo "  Issues:\n";
        foreach ($customer['issues'] as $issue) {
            echo "    - {$issue}\n";
        }
        echo "  Status breakdown: " . json_encode($customer['booking_status_counts']) . "\n";
        echo "\n";
    }
}

echo "SKIPPED CUSTOMERS\n";
echo "-----------------\n";
foreach ($skippedCustomers as $customer) {
    echo "Customer ID: {$customer['customer_id']}\n";
    echo "  Reason: {$customer['reason']}\n";
    echo "\n";
}

echo "DRY-RUN SUMMARY\n";
echo "---------------\n";
echo "Proposed INSERT operations: " . count($verifiedCustomers) . "\n";
echo "Each INSERT will include:\n";
echo "  - id: UUID (generated)\n";
echo "  - customer_id: from booking\n";
echo "  - provider_id: {$providerId}\n";
echo "  - visits: 0\n";
echo "  - joined_at: current timestamp\n";
echo "  - created_at: current timestamp\n";
echo "  - updated_at: current timestamp\n";
echo "\n";
echo "Conflict resolution: ON CONFLICT (customer_id, provider_id) DO NOTHING\n";
echo "This ensures no duplicate relationships will be created.\n";
