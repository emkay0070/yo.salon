<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$danglingMarkoId = '01a10a27-81d4-71eb-9713-d96efcb83929';
$markoEmail = 'marko_break@test.com';
$emoSpaProviderId = '01a0e836-39fa-700e-991d-797669bb5a32';

echo "=== INVITATION AUDIT FOR DANGLING MARKO ===" . PHP_EOL;
echo PHP_EOL;

// Search for invitations by email
echo "--- INVITATIONS BY EMAIL (marko_break@test.com) ---" . PHP_EOL;
$invitationsByEmail = \App\Models\Invitation::where('email', $markoEmail)->get();

if ($invitationsByEmail->isEmpty()) {
    echo "  No invitations found with this email" . PHP_EOL;
} else {
    echo "  Found {$invitationsByEmail->count()} invitation(s):" . PHP_EOL;
    foreach ($invitationsByEmail as $inv) {
        echo "  Invitation ID: {$inv->id}" . PHP_EOL;
        echo "    Provider ID: {$inv->provider_id}" . PHP_EOL;
        echo "    Location ID: " . ($inv->location_id ?? 'NULL') . PHP_EOL;
        echo "    Role: {$inv->role}" . PHP_EOL;
        echo "    Email: {$inv->email}" . PHP_EOL;
        echo "    Target ID: " . ($inv->target_id ?? 'NULL') . PHP_EOL;
        echo "    Status: {$inv->status}" . PHP_EOL;
        echo "    Created By: " . ($inv->created_by ?? 'NULL') . PHP_EOL;
        echo "    Accepted User ID: " . ($inv->accepted_user_id ?? 'NULL') . PHP_EOL;
        echo "    Accepted At: " . ($inv->accepted_at ?? 'NULL') . PHP_EOL;
        echo "    Expires At: " . ($inv->expires_at ?? 'NULL') . PHP_EOL;
        echo "    Created At: {$inv->created_at}" . PHP_EOL;
        echo "    Updated At: {$inv->updated_at}" . PHP_EOL;
        echo PHP_EOL;
    }
}

echo PHP_EOL;

// Search for invitations by provider (Emo Spa)
echo "--- INVITATIONS BY PROVIDER (Emo Spa) ---" . PHP_EOL;
$invitationsByProvider = \App\Models\Invitation::where('provider_id', $emoSpaProviderId)
    ->orderBy('created_at', 'desc')
    ->get();

echo "  Found {$invitationsByProvider->count()} invitation(s) for Emo Spa:" . PHP_EOL;
foreach ($invitationsByProvider as $inv) {
    echo "  Invitation ID: {$inv->id}" . PHP_EOL;
    echo "    Role: {$inv->role}" . PHP_EOL;
    echo "    Email: {$inv->email}" . PHP_EOL;
    echo "    Status: {$inv->status}" . PHP_EOL;
    echo "    Accepted At: " . ($inv->accepted_at ?? 'NULL') . PHP_EOL;
    echo "    Created At: {$inv->created_at}" . PHP_EOL;
    echo PHP_EOL;
}

echo PHP_EOL;

// Check if there are any invitations accepted around the time the Specialist was created
echo "--- INVITATIONS ACCEPTED AROUND 2026-10-05 03:42:03 ---" . PHP_EOL;
$timestamp = '2026-10-05 03:42:03';
$invitationsAroundTime = \App\Models\Invitation::where('accepted_at', '>=', '2026-10-05 03:30:00')
    ->where('accepted_at', '<=', '2026-10-05 04:00:00')
    ->get();

if ($invitationsAroundTime->isEmpty()) {
    echo "  No invitations accepted around this time" . PHP_EOL;
} else {
    echo "  Found {$invitationsAroundTime->count()} invitation(s) accepted around this time:" . PHP_EOL;
    foreach ($invitationsAroundTime as $inv) {
        echo "  Invitation ID: {$inv->id}" . PHP_EOL;
        echo "    Provider ID: {$inv->provider_id}" . PHP_EOL;
        echo "    Role: {$inv->role}" . PHP_EOL;
        echo "    Email: {$inv->email}" . PHP_EOL;
        echo "    Status: {$inv->status}" . PHP_EOL;
        echo "    Accepted At: {$inv->accepted_at}" . PHP_EOL;
        echo "    Accepted User ID: " . ($inv->accepted_user_id ?? 'NULL') . PHP_EOL;
        echo PHP_EOL;
    }
}

echo PHP_EOL;

// Check for any specialist-related invitations
echo "--- ALL SPECIALIST ROLE INVITATIONS ---" . PHP_EOL;
$specialistInvitations = \App\Models\Invitation::where('role', 'specialist')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "  Last 10 specialist invitations:" . PHP_EOL;
foreach ($specialistInvitations as $inv) {
    echo "  Invitation ID: {$inv->id}" . PHP_EOL;
    echo "    Provider ID: {$inv->provider_id}" . PHP_EOL;
    echo "    Email: {$inv->email}" . PHP_EOL;
    echo "    Status: {$inv->status}" . PHP_EOL;
    echo "    Accepted At: " . ($inv->accepted_at ?? 'NULL') . PHP_EOL;
    echo "    Created At: {$inv->created_at}" . PHP_EOL;
    echo PHP_EOL;
}

echo "=== AUDIT COMPLETE ===" . PHP_EOL;
