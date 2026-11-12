<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Verifying Customer::providers() relationship ===\n";

// Get a customer from recent bookings
$customer = \App\Models\Customer::where('phone', '+256700123456')->first();

if ($customer) {
    echo "Found customer: {$customer->id}\n";
    echo "Customer name: {$customer->name}\n";
    echo "Customer phone: {$customer->phone}\n";

    $providers = $customer->providers;
    echo "Providers count: " . $providers->count() . "\n";

    foreach ($providers as $provider) {
        echo "  Provider ID: {$provider->id}\n";
        echo "  Pivot data: " . json_encode($provider->pivot) . "\n";
    }

    // Also check raw table
    $cp = \DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->get();

    echo "\nRaw customer_providers count: " . $cp->count() . "\n";
    foreach ($cp as $row) {
        echo "  Provider ID: {$row->provider_id}, Visits: {$row->visits}\n";
    }
} else {
    echo "No customer found with phone +256700123456\n";
}

echo "\n=== Testing addSalonRelationship directly ===\n";

$provider = \App\Models\Provider::first();
$testCustomer = \App\Models\Customer::factory()->create([
    'name' => 'Test Customer',
    'phone' => '+256700999888',
    'email' => 'test@example.com',
]);

echo "Created test customer: {$testCustomer->id}\n";
echo "Provider ID: {$provider->id}\n";

$customerService = app(\App\Services\CustomerService::class);
$customerService->addSalonRelationship($testCustomer, $provider->id);

echo "Called addSalonRelationship\n";

$cpExists = \DB::table('customer_providers')
    ->where('customer_id', $testCustomer->id)
    ->where('provider_id', $provider->id)
    ->exists();

echo "customer_providers row exists: " . ($cpExists ? 'YES' : 'NO') . "\n";

if ($cpExists) {
    $providers = $testCustomer->providers;
    echo "Customer->providers() count: " . $providers->count() . "\n";
}
