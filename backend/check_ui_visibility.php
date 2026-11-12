<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Checking Why 3 Customers Are Not Visible in UI ===\n";
echo "Provider ID: {$providerId}\n\n";

// Get the 3 customers with customer_providers
$customers = \App\Models\Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    })
    ->with('bookings')
    ->get();

echo "Step 1: API Response Structure\n";
echo "Customers returned by API: {$customers->count()}\n\n";

foreach ($customers as $customer) {
    echo "Customer: {$customer->name}\n";
    echo "  ID: {$customer->id}\n";
    echo "  Phone: {$customer->phone}\n";
    echo "  Email: " . ($customer->email ?? 'NULL') . "\n";
    echo "  Visits: " . ($customer->visits ?? 'NULL') . "\n";
    echo "  Status: " . ($customer->status ?? 'NULL') . "\n";
    echo "  Bookings count: {$customer->bookings->count()}\n";

    // Check lastVisit calculation
    $lastBooking = $customer->bookings->where('status', 'completed')->sortByDesc('date')->first();
    echo "  Last visit: " . ($lastBooking ? $lastBooking->date : 'NULL') . "\n";
    echo "  isVip: " . ($customer->isVip ?? 'NULL') . "\n";
    echo "  is_vip: " . ($customer->is_vip ?? 'NULL') . "\n";
    echo "\n";
}

echo "Step 2: Check if any customers would be filtered out by frontend\n";

// Frontend filters:
// - matchesSearch: name, phone, email
// - matchesFilter: all, vip, new, inactive
// - status: 'active', 'inactive', 'new'

echo "Frontend filter checks:\n";
foreach ($customers as $customer) {
    $hasStatus = !empty($customer->status);
    $isVip = !empty($customer->isVip) || !empty($customer->is_vip);
    $hasName = !empty($customer->name);
    $hasPhone = !empty($customer->phone);

    echo "  {$customer->name}:\n";
    echo "    Has name: " . ($hasName ? 'YES' : 'NO') . "\n";
    echo "    Has phone: " . ($hasPhone ? 'YES' : 'NO') . "\n";
    echo "    Has status: " . ($hasStatus ? "YES ({$customer->status})" : 'NO') . "\n";
    echo "    Is VIP: " . ($isVip ? 'YES' : 'NO') . "\n";
    echo "\n";
}

echo "Step 3: Check if the API endpoint is being called correctly\n";
echo "Expected API call: GET /v1/customers\n";
echo "Expected headers: Authorization: Bearer {token}\n";
echo "Expected middleware: provider_id injected from context\n\n";

echo "Step 4: Check if there's a middleware issue\n";
echo "The CustomerController::index requires provider_id from request attributes\n";
echo "If middleware doesn't inject provider_id, it returns 403\n";
echo "If provider_id is wrong, it returns customers for wrong provider\n\n";

echo "CONCLUSION:\n";
echo "The API returns 3 customers, but UI shows 0.\n";
echo "Possible causes:\n";
echo "1. Frontend not rendering the data correctly\n";
echo "2. API returning 403/401 due to middleware issue\n";
echo "3. Frontend filtering out all 3 customers\n";
echo "4. React Query caching empty state\n";
echo "\n";
echo "Recommendation: Check browser DevTools Network tab to see actual API response.\n";
