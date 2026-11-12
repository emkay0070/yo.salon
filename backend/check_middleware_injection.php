<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$providerSlug = 'emo-spa';

echo "=== Checking Middleware Provider ID Injection ===\n";
echo "Provider ID: {$providerId}\n";
echo "Provider Slug: {$providerSlug}\n\n";

// Check if the provider exists and has the correct slug
$provider = \DB::table('providers')->where('id', $providerId)->first();

if ($provider) {
    echo "Provider found:\n";
    echo "  ID: {$provider->id}\n";
    echo "  Slug: {$provider->slug}\n";
    echo "  Type: {$provider->type}\n";
} else {
    echo "Provider NOT found\n";
}

echo "\n";

// Check the middleware that injects provider_id
echo "Checking middleware...\n";

// The frontend calls: GET /v1/customers
// The route is in api.php: Route::apiResource('customers', CustomerController::class);
// This route should have middleware that injects provider_id from the authenticated user's provider context

// Check if there's a BelongsToSalon or similar middleware
$middlewareFile = __DIR__ . '/app/Http/Middleware/BelongsToSalon.php';
if (file_exists($middlewareFile)) {
    echo "Found BelongsToSalon middleware\n";
    $middlewareContent = file_get_contents($middlewareFile);
    echo "Checking how it injects provider_id...\n";

    if (strpos($middlewareContent, 'provider_id') !== false) {
        echo "✓ Middleware handles provider_id\n";
    }
} else {
    echo "BelongsToSalon middleware not found\n";
}

echo "\n";

// Simulate what the middleware would do
echo "Simulating middleware injection...\n";

// Check if there's a user with provider context
echo "The frontend is calling from: /emo-spa/customers\n";
echo "This suggests the user is authenticated as the owner of 'emo-spa'\n";
echo "The middleware should inject provider_id: {$providerId}\n";

echo "\n";

// Check if the query would work with the correct provider_id
echo "Testing query with provider_id: {$providerId}\n";

$customers = \DB::table('customers')
    ->join('customer_providers', 'customers.id', '=', 'customer_providers.customer_id')
    ->where('customer_providers.provider_id', $providerId)
    ->select('customers.*')
    ->get();

echo "Customers found via direct query: {$customers->count()}\n";

foreach ($customers as $customer) {
    echo "  - {$customer->name} ({$customer->phone})\n";
}

echo "\n";

// Check if the Eloquent query works
echo "Testing Eloquent query...\n";

$eloquentCustomers = \App\Models\Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    })
    ->get();

echo "Customers found via Eloquent: {$eloquentCustomers->count()}\n";

foreach ($eloquentCustomers as $customer) {
    echo "  - {$customer->name} ({$customer->phone})\n";
}

echo "\n";

echo "CONCLUSION:\n";
echo "Both queries return {$customers->count()} customers.\n";
echo "If the API returns empty array, the middleware is likely NOT injecting provider_id,\n";
echo "or it's injecting a different/wrong provider_id.\n";
echo "\n";
echo "Recommendation: Add logging to CustomerController::index to log the actual provider_id\n";
echo "received from middleware to debug this issue.\n";
