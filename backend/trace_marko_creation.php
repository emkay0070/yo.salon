<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$danglingMarkoId = '01a10a27-81d4-71eb-9713-d96efcb83929';
$markoEmail = 'marko_break@test.com';

echo "=== TRACE SPECIALIST CREATION PROVENANCE ===" . PHP_EOL;
echo PHP_EOL;

// Get the Specialist
$specialist = \App\Models\Specialist::find($danglingMarkoId);

if (!$specialist) {
    echo "Specialist not found" . PHP_EOL;
    exit;
}

echo "SPECIALIST:" . PHP_EOL;
echo "  ID: {$specialist->id}" . PHP_EOL;
echo "  Name: {$specialist->name}" . PHP_EOL;
echo "  Email: {$specialist->email}" . PHP_EOL;
echo "  Provider ID: {$specialist->provider_id}" . PHP_EOL;
echo "  Created at: {$specialist->created_at}" . PHP_EOL;
echo "  Updated at: {$specialist->updated_at}" . PHP_EOL;
echo PHP_EOL;

// Check if this was created via the test command
echo "CHECKING TEST COMMAND ARTIFACTS:" . PHP_EOL;

// The test command creates a SpecialistAccount with marko_break@test.com
$testAccount = \App\Models\SpecialistAccount::where('email', $markoEmail)->first();

if ($testAccount) {
    echo "  Found SpecialistAccount with test email:" . PHP_EOL;
    echo "    Account ID: {$testAccount->id}" . PHP_EOL;
    echo "    Specialist ID: {$testAccount->specialist_id}" . PHP_EOL;
    echo "    Email: {$testAccount->email}" . PHP_EOL;
    echo "    Created at: {$testAccount->created_at}" . PHP_EOL;
    echo "    Updated at: {$testAccount->updated_at}" . PHP_EOL;
    
    if ($testAccount->specialist_id === $danglingMarkoId) {
        echo "    ✓ This account belongs to the dangling Marko" . PHP_EOL;
    } else {
        echo "    ✗ This account belongs to a DIFFERENT specialist ({$testAccount->specialist_id})" . PHP_EOL;
    }
} else {
    echo "  No SpecialistAccount found with test email" . PHP_EOL;
}

echo PHP_EOL;

// Check if there's a SpecialistAssignment that might have been the source
echo "CHECKING SPECIALIST ASSIGNMENT HISTORY:" . PHP_EOL;
$allAssignments = \App\Models\SpecialistAssignment::where('specialist_id', $danglingMarkoId)->get();

if ($allAssignments->isEmpty()) {
    echo "  No SpecialistAssignment records found for this specialist" . PHP_EOL;
    echo "  This confirms the specialist was created WITHOUT an assignment" . PHP_EOL;
} else {
    echo "  Found {$allAssignments->count()} assignment(s):" . PHP_EOL;
    foreach ($allAssignments as $assignment) {
        echo "    Assignment ID: {$assignment->id}" . PHP_EOL;
        echo "    Provider ID: {$assignment->provider_id}" . PHP_EOL;
        echo "    Status: {$assignment->status}" . PHP_EOL;
        echo "    Created at: {$assignment->created_at}" . PHP_EOL;
    }
}

echo PHP_EOL;

// Check if there's a Staff record
echo "CHECKING STAFF RECORD:" . PHP_EOL;
$staff = \App\Models\Staff::where('name', $specialist->name)
    ->where('provider_id', $specialist->provider_id)
    ->first();

if ($staff) {
    echo "  Found Staff record:" . PHP_EOL;
    echo "    Staff ID: {$staff->id}" . PHP_EOL;
    echo "    Created at: {$staff->created_at}" . PHP_EOL;
} else {
    echo "  No Staff record found" . PHP_EOL;
}

echo PHP_EOL;

// Check Laravel logs around the creation time
echo "CREATION TIME ANALYSIS:" . PHP_EOL;
$creationTime = $specialist->created_at;
echo "  Specialist created at: {$creationTime}" . PHP_EOL;
echo "  Time window to check: " . $creationTime->subMinutes(5) . " to " . $creationTime->addMinutes(10) . PHP_EOL;

echo PHP_EOL;

// Check if this might have been created via direct Specialist::create in some code
echo "POSSIBLE CREATION PATHS:" . PHP_EOL;
echo "  1. TestSpecialistBreaks command - creates SpecialistAccount but NOT Specialist" . PHP_EOL;
echo "  2. Invitation acceptance flow - creates Specialist + SpecialistAccount + SpecialistAssignment" . PHP_EOL;
echo "  3. SpecialistController::store() - creates Specialist only (no assignment)" . PHP_EOL;
echo "  4. Independent workspace provisioning - creates Specialist + SpecialistAssignment" . PHP_EOL;
echo "  5. Manual database insertion / seeder" . PHP_EOL;

echo PHP_EOL;

// Check if the specialist was created BEFORE the test account
echo "TIMELINE ANALYSIS:" . PHP_EOL;
if ($testAccount) {
    $specialistTime = $specialist->created_at;
    $accountTime = $testAccount->created_at;
    
    echo "  Specialist created: {$specialistTime}" . PHP_EOL;
    echo "  Account created: {$accountTime}" . PHP_EOL;
    
    if ($specialistTime->lt($accountTime)) {
        $diff = $specialistTime->diffInSeconds($accountTime);
        echo "  Specialist was created {$diff} seconds BEFORE the account" . PHP_EOL;
        echo "  This suggests the Specialist was created by a DIFFERENT mechanism" . PHP_EOL;
    } elseif ($specialistTime->gt($accountTime)) {
        $diff = $accountTime->diffInSeconds($specialistTime);
        echo "  Specialist was created {$diff} seconds AFTER the account" . PHP_EOL;
        echo "  This is IMPOSSIBLE - account requires specialist_id" . PHP_EOL;
    } else {
        echo "  Created at the same time (within database precision)" . PHP_EOL;
    }
}

echo PHP_EOL;

// Final conclusion
echo "=== CONCLUSION ===" . PHP_EOL;
echo "The dangling Marko specialist was created with:" . PHP_EOL;
echo "  - provider_id = Emo Spa" . PHP_EOL;
echo "  - NO SpecialistAssignment" . PHP_EOL;
echo "  - NO Staff record" . PHP_EOL;
echo "  - A SpecialistAccount with test email (marko_break@test.com)" . PHP_EOL;
echo PHP_EOL;
echo "This is NOT the expected behavior from:" . PHP_EOL;
echo "  - Invitation acceptance (would create assignment)" . PHP_EOL;
echo "  - Independent workspace provisioning (would create assignment)" . PHP_EOL;
echo "  - TestSpecialistBreaks command (uses existing specialist)" . PHP_EOL;
echo PHP_EOL;
echo "Most likely cause: Direct Specialist::create() call without assignment creation," . PHP_EOL;
echo "or manual database insertion during testing." . PHP_EOL;
