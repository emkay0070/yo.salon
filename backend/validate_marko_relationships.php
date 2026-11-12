<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$danglingMarkoId = '01a10a27-81d4-71eb-9713-d96efcb83929';
$independentMarkoId = '01a0f20e-55bf-70d9-b6d3-dfdd628f1914';
$emoSpaProviderId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== MARKO RECORDS: COMPLETE RELATIONSHIP AUDIT ===" . PHP_EOL;
echo PHP_EOL;

function printSpecialistAudit($specialistId, $label) {
    echo "--- {$label} ---" . PHP_EOL;
    
    $specialist = \App\Models\Specialist::with(['provider', 'assignments', 'assignments.location', 'assignments.specialist', 'account'])->find($specialistId);
    
    if (!$specialist) {
        echo "  Specialist NOT FOUND" . PHP_EOL;
        echo PHP_EOL;
        return;
    }
    
    echo "SPECIALIST:" . PHP_EOL;
    echo "  ID: {$specialist->id}" . PHP_EOL;
    echo "  Name: {$specialist->name}" . PHP_EOL;
    echo "  Email: {$specialist->email}" . PHP_EOL;
    echo "  Phone: {$specialist->phone}" . PHP_EOL;
    echo "  Handle: {$specialist->handle}" . PHP_EOL;
    echo "  Active: " . ($specialist->active ? 'true' : 'false') . PHP_EOL;
    echo "  Created at: {$specialist->created_at}" . PHP_EOL;
    echo "  Updated at: {$specialist->updated_at}" . PHP_EOL;
    
    // User/Person relationship
    echo PHP_EOL . "USER/PERSON:" . PHP_EOL;
    $account = $specialist->account;
    if ($account) {
        echo "  SpecialistAccount ID: {$account->id}" . PHP_EOL;
        echo "  Email: {$account->email}" . PHP_EOL;
        echo "  Phone: {$account->phone}" . PHP_EOL;
        echo "  Is Active: " . ($account->is_active ? 'true' : 'false') . PHP_EOL;
        echo "  Onboarding Completed: " . ($account->onboarding_completed_at ?? 'NULL') . PHP_EOL;
    } else {
        echo "  No associated SpecialistAccount" . PHP_EOL;
    }
    
    // Provider relationship
    echo PHP_EOL . "PROVIDER:" . PHP_EOL;
    if ($specialist->provider) {
        echo "  Provider ID: {$specialist->provider->id}" . PHP_EOL;
        echo "  Provider Name: {$specialist->provider->display_name}" . PHP_EOL;
        echo "  Provider Slug: {$specialist->provider->slug}" . PHP_EOL;
        echo "  Provider Type: {$specialist->provider->type}" . PHP_EOL;
        echo "  Provider Active: " . ($specialist->provider->active ? 'true' : 'false') . PHP_EOL;
    } else {
        echo "  No associated provider" . PHP_EOL;
    }
    
    // SpecialistAssignment relationships
    echo PHP_EOL . "SPECIALIST ASSIGNMENTS:" . PHP_EOL;
    $assignments = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)->get();
    
    if ($assignments->isEmpty()) {
        echo "  No assignments found" . PHP_EOL;
    } else {
        foreach ($assignments as $assignment) {
            echo "  Assignment ID: {$assignment->id}" . PHP_EOL;
            echo "    Specialist ID: {$assignment->specialist_id}" . PHP_EOL;
            echo "    Provider ID: {$assignment->provider_id}" . PHP_EOL;
            echo "    Location ID: " . ($assignment->location_id ?? 'NULL (provider-wide)') . PHP_EOL;
            echo "    Staff ID: " . ($assignment->staff_id ?? 'NULL') . PHP_EOL;
            echo "    Status: {$assignment->status}" . PHP_EOL;
            echo "    Employment Type: {$assignment->employment_type}" . PHP_EOL;
            echo "    Created at: {$assignment->created_at}" . PHP_EOL;
            echo "    Updated at: {$assignment->updated_at}" . PHP_EOL;
            
            // Location details
            if ($assignment->location) {
                echo "    Location Name: {$assignment->location->name}" . PHP_EOL;
                echo "    Location Address: {$assignment->location->address}" . PHP_EOL;
                echo "    Location Is Primary: " . ($assignment->location->is_primary ? 'true' : 'false') . PHP_EOL;
            }
            
            echo PHP_EOL;
        }
    }
    
    // Staff record check
    echo PHP_EOL . "STAFF RECORD (operational):" . PHP_EOL;
    $staffRecords = \App\Models\Staff::where('name', $specialist->name)
        ->where('provider_id', $specialist->provider_id)
        ->get();
    
    if ($staffRecords->isEmpty()) {
        echo "  No Staff records found" . PHP_EOL;
    } else {
        foreach ($staffRecords as $staff) {
            echo "  Staff ID: {$staff->id}" . PHP_EOL;
            echo "    Name: {$staff->name}" . PHP_EOL;
            echo "    Provider ID: {$staff->provider_id}" . PHP_EOL;
            echo "    Location ID: " . ($staff->location_id ?? 'NULL') . PHP_EOL;
            echo "    User ID: " . ($staff->user_id ?? 'NULL') . PHP_EOL;
            echo "    Active: " . ($staff->active ? 'true' : 'false') . PHP_EOL;
            echo "    Created at: {$staff->created_at}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL;
}

// Audit both Marko records
printSpecialistAudit($danglingMarkoId, "DANGLING MARKO (Emo Spa)");
printSpecialistAudit($independentMarkoId, "INDEPENDENT MARKO");

// Additional cross-check: Show all SpecialistAssignments for Emo Spa
echo "--- EMO SPA: ALL SPECIALIST ASSIGNMENTS ---" . PHP_EOL;
$emoSpaAssignments = \App\Models\SpecialistAssignment::where('provider_id', $emoSpaProviderId)
    ->with(['specialist', 'location'])
    ->get();

echo "Total assignments: {$emoSpaAssignments->count()}" . PHP_EOL;
foreach ($emoSpaAssignments as $assignment) {
    echo "  Assignment ID: {$assignment->id}" . PHP_EOL;
    echo "    Specialist: {$assignment->specialist->name} ({$assignment->specialist_id})" . PHP_EOL;
    echo "    Location: " . ($assignment->location ? $assignment->location->name : 'NULL (provider-wide)') . PHP_EOL;
    echo "    Status: {$assignment->status}" . PHP_EOL;
    echo "    Employment Type: {$assignment->employment_type}" . PHP_EOL;
    echo "    Staff ID: " . ($assignment->staff_id ?? 'NULL') . PHP_EOL;
    echo PHP_EOL;
}

echo "=== AUDIT COMPLETE ===" . PHP_EOL;
