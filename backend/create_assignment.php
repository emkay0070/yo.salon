<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SpecialistAssignment;

$specialistId = $argv[1] ?? null;
$providerId = $argv[2] ?? null;
$locationId = $argv[3] ?? null;

if (!$specialistId || !$providerId || !$locationId) {
    echo "Error: Missing arguments\n";
    exit(1);
}

$exists = SpecialistAssignment::where('specialist_id', $specialistId)
    ->where('provider_id', $providerId)
    ->where('location_id', $locationId)
    ->exists();

if (!$exists) {
    SpecialistAssignment::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'specialist_id' => $specialistId,
        'provider_id' => $providerId,
        'location_id' => $locationId,
        'status' => 'ACTIVE',
    ]);
    echo "Created assignment for specialist\n";
} else {
    echo "Assignment already exists for specialist\n";
}
