<?php

/**
 * Phase 1C Final Integrity Check (Updated)
 *
 * Independent workspace specialists legitimately have NULL staff_id.
 * Only salon-employed specialists require a staff_id.
 * 
 * This script distinguishes between the two cases.
 *
 * Run: php database/scripts/verify_specialist_staff_alignment.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== Specialist ↔ Staff Alignment Integrity Check ===\n\n";

$total = DB::table('specialist_assignments')->count();
$withStaff = DB::table('specialist_assignments')->whereNotNull('staff_id')->count();
$nullStaff = DB::table('specialist_assignments')->whereNull('staff_id')->count();

echo "Total SpecialistAssignments   : {$total}\n";
echo "Linked   (staff_id NOT NULL)  : {$withStaff}\n";
echo "Unlinked (staff_id IS NULL)   : {$nullStaff}\n\n";

// Classify unlinked assignments
$unlinked = DB::table('specialist_assignments as sa')
    ->join('specialists as sp', 'sp.id', '=', 'sa.specialist_id')
    ->leftJoin('salons as s', 's.id', '=', 'sa.salon_id')
    ->whereNull('sa.staff_id')
    ->select(
        'sa.id as assignment_id',
        'sa.specialist_id',
        'sp.name as specialist_name',
        'sp.work_preference',
        'sa.salon_id',
        's.name as salon_name',
        'sa.employment_type',
        'sa.status',
    )
    ->get();

$intentionalNulls = 0;
$problemNulls = 0;
$problemRows = [];

foreach ($unlinked as $row) {
    // Independent workspace specialists (work_preference = 'independent' or 'freelance')
    // OR specialists who own their own workspace salon
    $isIndependentOperator = in_array(strtolower($row->work_preference ?? ''), ['independent', 'freelance', 'both'])
        || in_array(strtoupper($row->employment_type ?? ''), ['FREELANCER', 'CONTRACTOR']);

    // Also check: does the salon name suggest it's their own workspace?
    $isWorkspaceSalon = str_contains(strtolower($row->salon_name ?? ''), 'workspace')
        || str_contains(strtolower($row->salon_name ?? ''), strtolower($row->specialist_name ?? ''));

    if ($isIndependentOperator || $isWorkspaceSalon) {
        $intentionalNulls++;
        echo "✅ INTENTIONAL NULL — Independent specialist: {$row->specialist_name}\n";
        echo "   Workspace: {$row->salon_name} | work_preference: {$row->work_preference} | employment_type: {$row->employment_type}\n\n";
    } else {
        $problemNulls++;
        $problemRows[] = $row;
        echo "❌ PROBLEM NULL — Salon-employed specialist with no Staff link:\n";
        echo "   Specialist: {$row->specialist_name} ({$row->specialist_id})\n";
        echo "   Salon: {$row->salon_name} ({$row->salon_id})\n\n";
    }
}

echo "--- Duplicate specialist/salon check ---\n";
$duplicates = DB::table('specialist_assignments')
    ->select('specialist_id', 'salon_id', DB::raw('COUNT(*) as cnt'))
    ->groupBy('specialist_id', 'salon_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();

if ($duplicates->count() > 0) {
    echo "⚠️  {$duplicates->count()} duplicate specialist/salon pair(s) found.\n";
    foreach ($duplicates as $d) {
        echo "  - Specialist {$d->specialist_id} at Salon {$d->salon_id}: {$d->cnt} rows\n";
    }
} else {
    echo "✅ No duplicate specialist/salon pairs found.\n";
}

echo "\n--- Salon mismatch check (staff.salon_id vs assignment.salon_id) ---\n";
$mismatches = DB::table('specialist_assignments as sa')
    ->join('staff as s', 's.id', '=', 'sa.staff_id')
    ->whereRaw('sa.salon_id != s.salon_id')
    ->whereNotNull('sa.staff_id')
    ->select('sa.id', 'sa.specialist_id', 'sa.salon_id as assignment_salon', 's.salon_id as staff_salon')
    ->get();

if ($mismatches->count() > 0) {
    echo "⚠️  {$mismatches->count()} salon mismatch(es) found.\n";
} else {
    echo "✅ All staff.salon_id values match assignment.salon_id.\n";
}

echo "\n=== Summary ===\n";
echo "Linked correctly      : {$withStaff}\n";
echo "Intentional NULLs     : {$intentionalNulls} (independent workspace specialists)\n";
echo "Problem NULLs         : {$problemNulls} (salon-employed, unlinked)\n";
echo "Duplicates            : {$duplicates->count()}\n";
echo "Salon mismatches      : {$mismatches->count()}\n";

echo "\n=== Verdict ===\n";
if ($problemNulls === 0 && $duplicates->count() === 0 && $mismatches->count() === 0) {
    echo "✅ CLEAN: Data is correctly aligned.\n";
    echo "   Independent workspace specialists correctly have NULL staff_id.\n";
    echo "   Salon-employed specialists are all linked to Staff records.\n";
    echo "\n   ✅ Safe to proceed with Phase 1D architecture decision.\n";
    echo "   NOTE: Phase 1D will NOT enforce NOT NULL globally.\n";
    echo "   staff_id is nullable for independent specialists by design.\n";
} else {
    echo "❌ ISSUES FOUND: Resolve {$problemNulls} problem NULL(s) before Phase 1D.\n";
}
echo "\n";
