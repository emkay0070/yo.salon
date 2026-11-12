<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ScheduleMigrationService;

$migrationService = new ScheduleMigrationService();

echo "=== Testing Schedule Migration Service ===\n\n";

// Test 1: Get providers needing migration
echo "1. Checking providers needing migration...\n";
$providers = $migrationService->getProvidersNeedingMigration();
echo "   Found " . count($providers) . " providers needing migration\n";
foreach ($providers as $p) {
    echo "   - {$p['provider_name']} (Location: {$p['location_name']})\n";
}

if (empty($providers)) {
    echo "\nNo providers need migration. Creating a test case...\n";
    
    // Find a provider with opening_hours to test with
    $location = \App\Models\Location::whereNotNull('opening_hours')->first();
    if ($location) {
        echo "Testing with existing location: {$location->name}\n";
        
        // Check if it needs migration
        $needsMigration = $migrationService->needsMigration($location->provider_id);
        echo "Needs migration: " . ($needsMigration ? 'YES' : 'NO') . "\n";
        
        if (!$needsMigration) {
            echo "This provider already has canonical schedules. Testing migration service anyway...\n";
            
            // Delete existing schedules to test migration
            \App\Models\LocationSchedule::where('location_id', $location->id)->delete();
            \App\Models\ProviderSchedule::where('provider_id', $location->provider_id)->delete();
            echo "Deleted existing canonical schedules\n";
            
            $needsMigration = $migrationService->needsMigration($location->provider_id);
            echo "Now needs migration: " . ($needsMigration ? 'YES' : 'NO') . "\n";
        }
        
        // Test migration
        echo "\n2. Running migration...\n";
        $result = $migrationService->migrateProvider($location->provider_id);
        echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
        echo "   Message: {$result['message']}\n";
        if ($result['success']) {
            echo "   Location schedules created: {$result['location_schedules_created']}\n";
            echo "   Provider schedules created: {$result['provider_schedules_created']}\n";
        }
        
        // Verify
        echo "\n3. Verifying migration...\n";
        $locationSchedules = \App\Models\LocationSchedule::where('location_id', $location->id)->count();
        $providerSchedules = \App\Models\ProviderSchedule::where('provider_id', $location->provider_id)->count();
        echo "   LocationSchedule records: {$locationSchedules}\n";
        echo "   ProviderSchedule records: {$providerSchedules}\n";
        
        // Check if still needs migration
        $stillNeeds = $migrationService->needsMigration($location->provider_id);
        echo "   Still needs migration: " . ($stillNeeds ? 'YES' : 'NO') . "\n";
    } else {
        echo "No location with opening_hours found for testing\n";
    }
} else {
    echo "\n2. Testing migration on first provider...\n";
    $firstProvider = $providers[0];
    echo "   Provider: {$firstProvider['provider_name']}\n";
    
    $result = $migrationService->migrateProvider($firstProvider['provider_id']);
    echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "   Message: {$result['message']}\n";
    if ($result['success']) {
        echo "   Location schedules created: {$result['location_schedules_created']}\n";
        echo "   Provider schedules created: {$result['provider_schedules_created']}\n";
    }
}

echo "\n=== Test Complete ===\n";
