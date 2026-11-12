<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
$specialistId = '01a0e836-3a6a-735a-a769-5be031d25a04';
$providerSlug = 'emo-spa';

echo "=== Checking Location Access Issue ===\n";
echo "Provider ID: {$providerId}\n";
echo "Specialist ID: {$specialistId}\n";
echo "Provider Slug: {$providerSlug}\n\n";

// Get all assignments for this specialist at this provider
$assignments = \DB::table('specialist_assignments')
    ->where('specialist_id', $specialistId)
    ->where('provider_id', $providerId)
    ->whereIn('status', ['ACTIVE', 'active'])
    ->get();

echo "Active assignments: {$assignments->count()}\n";

foreach ($assignments as $assignment) {
    echo "  Assignment ID: {$assignment->id}\n";
    echo "  Location ID: " . ($assignment->location_id ?? 'NULL (provider-wide)') . "\n";
    echo "  Status: {$assignment->status}\n";
}

echo "\n";

// Check if specialist has provider-wide access
$hasProviderWideAccess = $assignments->contains(fn($a) => $a->location_id === null);

echo "Has provider-wide access: " . ($hasProviderWideAccess ? 'YES' : 'NO') . "\n";

if (!$hasProviderWideAccess) {
    echo "\n⚠️  ISSUE IDENTIFIED:\n";
    echo "Specialist has only location-specific access (location_id is not NULL)\n";
    echo "But the frontend is calling /v1/specialist-portal/customers?provider_slug={$providerSlug}\n";
    echo "WITHOUT location_slug parameter\n";
    echo "\n";
    echo "The controller will return:\n";
    echo "HTTP 400: 'Location selection required - you have location-specific access only'\n";
    echo "\n";
    echo "This causes the Clients page to show 0 customers.\n";
}

echo "\n=== Solution ===\n";
echo "The frontend must include location_slug in the API request:\n";
echo "GET /v1/specialist-portal/customers?provider_slug={$providerSlug}&location_slug={location_slug}\n";
echo "\n";

// Get the location slug
$locationId = $assignments->first()->location_id;
$location = \DB::table('locations')->where('id', $locationId)->first();

if ($location) {
    echo "Location ID: {$locationId}\n";
    echo "Location slug: {$location->slug}\n";
    echo "\n";
    echo "Expected API call:\n";
    echo "GET /v1/specialist-portal/customers?provider_slug={$providerSlug}&location_slug={$location->slug}\n";
}
