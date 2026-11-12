<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emoSpaProviderId = '01a0e836-39fa-700e-991d-797669bb5a32';
$danglingMarkoId = '01a10a27-81d4-71eb-9713-d96efcb83929';
$independentMarkoId = '01a0f20e-55bf-70d9-b6d3-dfdd628f1914';

echo "=== VALIDATION: ASSIGNABLE SPECIALISTS ENDPOINT ===" . PHP_EOL;
echo PHP_EOL;

// Test 1: Dangling Marko should NOT be returned
echo "Test 1: Dangling Marko (Emo Spa) should NOT be assignable" . PHP_EOL;
$danglingMarko = \App\Models\Specialist::find($danglingMarkoId);
if ($danglingMarko) {
    echo "  Specialist exists: {$danglingMarko->name}" . PHP_EOL;
    echo "  provider_id: {$danglingMarko->provider_id}" . PHP_EOL;
    echo "  active: " . ($danglingMarko->active ? 'true' : 'false') . PHP_EOL;
    
    // Check if he has active assignments
    $activeAssignments = \App\Models\SpecialistAssignment::where('specialist_id', $danglingMarkoId)
        ->where('provider_id', $emoSpaProviderId)
        ->whereIn('status', ['active', 'ACTIVE'])
        ->count();
    echo "  Active assignments for Emo Spa: {$activeAssignments}" . PHP_EOL;
    
    // Simulate the assignable-specialists query
    $isAssignable = \App\Models\Specialist::where('provider_id', $emoSpaProviderId)
        ->where('active', true)
        ->where('id', $danglingMarkoId)
        ->whereHas('assignments', function ($query) use ($emoSpaProviderId) {
            $query->where('provider_id', $emoSpaProviderId)
                  ->whereIn('status', ['active', 'ACTIVE']);
        })
        ->exists();
    
    echo "  Would be returned by assignable-specialists: " . ($isAssignable ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
} else {
    echo "  Dangling Marko not found (PASS)" . PHP_EOL;
}
echo PHP_EOL;

// Test 2: Independent Marko should NOT be returned for Emo Spa
echo "Test 2: Independent Marko should NOT be assignable to Emo Spa" . PHP_EOL;
$independentMarko = \App\Models\Specialist::find($independentMarkoId);
if ($independentMarko) {
    echo "  Specialist exists: {$independentMarko->name}" . PHP_EOL;
    echo "  provider_id: {$independentMarko->provider_id}" . PHP_EOL;
    
    // Simulate the assignable-specialists query
    $isAssignable = \App\Models\Specialist::where('provider_id', $emoSpaProviderId)
        ->where('active', true)
        ->where('id', $independentMarkoId)
        ->whereHas('assignments', function ($query) use ($emoSpaProviderId) {
            $query->where('provider_id', $emoSpaProviderId)
                  ->whereIn('status', ['active', 'ACTIVE']);
        })
        ->exists();
    
    echo "  Would be returned by assignable-specialists for Emo Spa: " . ($isAssignable ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
} else {
    echo "  Independent Marko not found" . PHP_EOL;
}
echo PHP_EOL;

// Test 3: Real Emo Spa specialists with active assignments SHOULD be returned
echo "Test 3: Real Emo Spa specialists with active assignments SHOULD be assignable" . PHP_EOL;
$realSpecialists = \App\Models\Specialist::where('provider_id', $emoSpaProviderId)
    ->where('active', true)
    ->whereHas('assignments', function ($query) use ($emoSpaProviderId) {
        $query->where('provider_id', $emoSpaProviderId)
              ->whereIn('status', ['active', 'ACTIVE']);
    })
    ->get();

echo "  Found {$realSpecialists->count()} assignable specialists:" . PHP_EOL;
foreach ($realSpecialists as $spec) {
    echo "    - {$spec->name} (id: {$spec->id})" . PHP_EOL;
}
echo PHP_EOL;

// Test 4: Provider-wide assignments (location_id = NULL) should be eligible
echo "Test 4: Provider-wide assignments (location_id = NULL) should be eligible" . PHP_EOL;
$providerWideAssignments = \App\Models\SpecialistAssignment::where('provider_id', $emoSpaProviderId)
    ->whereIn('status', ['active', 'ACTIVE'])
    ->whereNull('location_id')
    ->get();

echo "  Found {$providerWideAssignments->count()} provider-wide active assignments:" . PHP_EOL;
foreach ($providerWideAssignments as $assignment) {
    $specialist = $assignment->specialist;
    if ($specialist) {
        echo "    - {$specialist->name} (assignment location_id: NULL)" . PHP_EOL;
        
        // Verify they would be returned
        $isAssignable = \App\Models\Specialist::where('provider_id', $emoSpaProviderId)
            ->where('active', true)
            ->where('id', $specialist->id)
            ->whereHas('assignments', function ($query) use ($emoSpaProviderId) {
                $query->where('provider_id', $emoSpaProviderId)
                      ->whereIn('status', ['active', 'ACTIVE']);
            })
            ->exists();
        echo "      Would be returned: " . ($isAssignable ? 'YES (PASS)' : 'NO (FAIL)') . PHP_EOL;
    }
}
echo PHP_EOL;

// Test 5: Existing general specialists endpoint should still work
echo "Test 5: Existing general specialists endpoint should still return all active specialists" . PHP_EOL;
$allSpecialists = \App\Models\Specialist::where('provider_id', $emoSpaProviderId)
    ->where('active', true)
    ->get();

echo "  Found {$allSpecialists->count()} active specialists (including those without assignments):" . PHP_EOL;
foreach ($allSpecialists as $spec) {
    $assignmentCount = \App\Models\SpecialistAssignment::where('specialist_id', $spec->id)
        ->where('provider_id', $emoSpaProviderId)
        ->whereIn('status', ['active', 'ACTIVE'])
        ->count();
    echo "    - {$spec->name} (active assignments: {$assignmentCount})" . PHP_EOL;
}
echo PHP_EOL;

echo "=== VALIDATION COMPLETE ===" . PHP_EOL;
