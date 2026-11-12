<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== Emo Spa Records ===\n\n";

$provider = \App\Models\Provider::find($providerId);
echo "Provider:\n";
echo "  ID: {$provider->id}\n";
echo "  Name: {$provider->display_name}\n";
echo "  Type: {$provider->type}\n\n";

$locations = \App\Models\Location::where('provider_id', $providerId)->get();
echo "Locations (count: {$locations->count()}):\n";
foreach ($locations as $loc) {
    echo "  ID: {$loc->id}\n";
    echo "  Name: {$loc->name}\n";
    echo "  opening_hours: " . (is_array($loc->opening_hours) ? json_encode($loc->opening_hours) : ($loc->opening_hours ?? 'NULL')) . "\n\n";
}

$providerSchedules = \App\Models\ProviderSchedule::where('provider_id', $providerId)->get();
echo "ProviderSchedule records (count: {$providerSchedules->count()}):\n";
foreach ($providerSchedules as $ps) {
    echo "  Day: {$ps->day_of_week}, Open: {$ps->open_time}, Close: {$ps->close_time}\n";
}
echo "\n";

$locationSchedules = \App\Models\LocationSchedule::whereIn('location_id', $locations->pluck('id'))->get();
echo "LocationSchedule records (count: {$locationSchedules->count()}):\n";
foreach ($locationSchedules as $ls) {
    echo "  Location: {$ls->location_id}, Day: {$ls->day_of_week}, Open: {$ls->open_time}, Close: {$ls->close_time}\n";
}
