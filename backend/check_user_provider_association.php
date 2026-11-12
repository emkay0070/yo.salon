<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Checking User Provider Association ===\n";
echo "Expected Provider ID: {$providerId}\n\n";

// Get all users
$users = \DB::table('users')->get();

echo "Total users in system: {$users->count()}\n\n";

foreach ($users as $user) {
    echo "User ID: {$user->id}\n";
    echo "  Email: {$user->email}\n";
    echo "  Status: {$user->status}\n";

    // Check if user has provider_users relationship
    $providerUser = \DB::table('provider_users')
        ->where('user_id', $user->id)
        ->first();

    if ($providerUser) {
        echo "  ✓ Has provider_users relationship\n";
        echo "    Provider ID: {$providerUser->provider_id}\n";

        if ($providerUser->provider_id === $providerId) {
            echo "    ⭐ This is the expected provider!\n";
        } else {
            echo "    ⚠️  This is a DIFFERENT provider!\n";
        }
    } else {
        echo "  ✗ NO provider_users relationship\n";
    }

    echo "\n";
}

echo "=== Checking currentProvider() method ===\n";

// Simulate what currentProvider() does
// It likely queries provider_users where user_id = auth()->id()

$currentUserProvider = \DB::table('provider_users')
    ->first();

if ($currentUserProvider) {
    echo "Current provider for authenticated user:\n";
    echo "  Provider ID: {$currentUserProvider->provider_id}\n";
    echo "  User ID: {$currentUserProvider->user_id}\n";

    if ($currentUserProvider->provider_id === $providerId) {
        echo "  ✓ Matches expected provider\n";
    } else {
        echo "  ⚠️  DOES NOT match expected provider!\n";
        echo "  This explains why the API returns empty array - it's querying for the wrong provider!\n";
    }
} else {
    echo "No provider_users relationship found\n";
}
