<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "Checking schedules for provider: $providerId\n";
echo "Provider type: " . \App\Models\Provider::find($providerId)->type . "\n\n";

$schedules = \App\Models\ProviderSchedule::where('provider_id', $providerId)->get();
echo "ProviderSchedule count: " . $schedules->count() . "\n";

foreach ($schedules as $s) {
    echo "  Day: {$s->day_of_week}, Open: {$s->open_time}, Close: {$s->close_time}, Status: " . ($s->status ?? 'NULL') . "\n";
}

echo "\n";

// Check with the same query as AvailabilityEngine
$hasProviderSchedule = \App\Models\ProviderSchedule::where('provider_id', $providerId)
    ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
    ->count() >= 1;

echo "AvailabilityEngine check result: " . ($hasProviderSchedule ? 'PASS' : 'FAIL') . "\n";
