<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Schedule Migration Script ===\n";
echo "Migrating opening_hours JSON to canonical schedule tables\n\n";

$dayMap = [
    'monday' => 1,
    'tuesday' => 2,
    'wednesday' => 3,
    'thursday' => 4,
    'friday' => 5,
    'saturday' => 6,
    'sunday' => 0,
];

// Get all locations with opening_hours but no LocationSchedule records
$locations = \App\Models\Location::whereNotNull('opening_hours')
    ->whereDoesntHave('schedules')
    ->get();

echo "Found {$locations->count()} locations needing migration\n\n";

foreach ($locations as $location) {
    echo "Processing location: {$location->name} (Provider: {$location->provider->display_name})\n";
    
    $openingHours = $location->opening_hours;
    if (!is_array($openingHours)) {
        echo "  - Skipping: invalid opening_hours format\n";
        continue;
    }
    
    $workingDays = array_keys($openingHours);
    $schedulesCreated = 0;
    
    // Create LocationSchedule records
    foreach ($dayMap as $frontendDay => $dayOfWeekInt) {
        if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
            $hours = $openingHours[$frontendDay];
            \App\Models\LocationSchedule::updateOrCreate(
                [
                    'location_id' => $location->id,
                    'day_of_week' => $dayOfWeekInt,
                ],
                [
                    'open_time' => $hours['open'] . ':00',
                    'close_time' => $hours['close'] . ':00',
                    'is_closed' => false,
                ]
            );
            $schedulesCreated++;
        } else {
            // Mark non-working days as closed
            \App\Models\LocationSchedule::updateOrCreate(
                [
                    'location_id' => $location->id,
                    'day_of_week' => $dayOfWeekInt,
                ],
                [
                    'open_time' => null,
                    'close_time' => null,
                    'is_closed' => true,
                ]
            );
            $schedulesCreated++;
        }
    }
    
    echo "  - Created {$schedulesCreated} LocationSchedule records\n";
    
    // Also create ProviderSchedule records for the provider
    $provider = $location->provider;
    $providerSchedulesCreated = 0;
    
    foreach ($dayMap as $frontendDay => $dayOfWeekInt) {
        if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
            $hours = $openingHours[$frontendDay];
            \App\Models\ProviderSchedule::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'day_of_week' => $dayOfWeekInt,
                ],
                [
                    'open_time' => $hours['open'] . ':00',
                    'close_time' => $hours['close'] . ':00',
                    'is_closed' => false,
                ]
            );
            $providerSchedulesCreated++;
        } else {
            \App\Models\ProviderSchedule::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'day_of_week' => $dayOfWeekInt,
                ],
                [
                    'open_time' => null,
                    'close_time' => null,
                    'is_closed' => true,
                ]
            );
            $providerSchedulesCreated++;
        }
    }
    
    echo "  - Created {$providerSchedulesCreated} ProviderSchedule records\n\n";
}

echo "=== Migration Complete ===\n";
echo "Verifying results...\n";
echo "Locations with LocationSchedule: " . \App\Models\LocationSchedule::distinct('location_id')->count() . "\n";
echo "Providers with ProviderSchedule: " . \App\Models\ProviderSchedule::distinct('provider_id')->count() . "\n";
