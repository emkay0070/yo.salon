<?php

/**
 * Diagnose: why do Specialist records not have matching Staff records?
 * This script dumps Staff and Specialist IDs side by side for comparison.
 *
 * Run: php database/scripts/diagnose_staff_specialist_ids.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== Staff Records ===\n";
$staff = DB::table('staff')
    ->join('salons', 'salons.id', '=', 'staff.salon_id')
    ->select('staff.id', 'staff.name', 'staff.salon_id', 'salons.name as salon_name', 'staff.role')
    ->orderBy('staff.name')
    ->get();

echo "Total staff: {$staff->count()}\n\n";
foreach ($staff as $s) {
    echo "  [{$s->name}] id={$s->id} salon={$s->salon_name}\n";
}

echo "\n=== Specialist Records ===\n";
$specialists = DB::table('specialists')
    ->leftJoin('salons', 'salons.id', '=', DB::raw("(SELECT salon_id FROM specialist_assignments WHERE specialist_id = specialists.id LIMIT 1)"))
    ->select('specialists.id', 'specialists.name', 'salons.name as salon_name')
    ->orderBy('specialists.name')
    ->get();

echo "Total specialists: {$specialists->count()}\n\n";
foreach ($specialists as $sp) {
    $hasStaff = DB::table('staff')->where('id', $sp->id)->exists();
    $staffTag = $hasStaff ? '✅ has Staff match' : '❌ NO Staff match';
    echo "  [{$sp->name}] id={$sp->id} salon={$sp->salon_name} — {$staffTag}\n";
}

echo "\n=== Cross-reference: Specialists whose ID exists in Staff ===\n";
$matched = DB::table('specialists')
    ->join('staff', 'staff.id', '=', 'specialists.id')
    ->select('specialists.id', 'specialists.name as specialist_name', 'staff.name as staff_name', 'staff.salon_id')
    ->get();
echo "Matched: {$matched->count()}\n";
foreach ($matched as $m) {
    echo "  [{$m->specialist_name}] id={$m->id} (Staff name: {$m->staff_name})\n";
}

echo "\n=== Conclusion ===\n";
if ($matched->count() === 0) {
    echo "❌ No Specialists have matching Staff IDs. The 'shared UUID' invariant was NOT maintained.\n";
    echo "   Specialists and Staff were created independently with different UUIDs.\n";
    echo "   You will need to manually link them via SpecialistAssignment.staff_id.\n";
} else {
    echo "✅ {$matched->count()} Specialist(s) have matching Staff records.\n";
}
