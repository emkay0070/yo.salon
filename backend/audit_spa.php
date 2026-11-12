<?php
/**
 * Read-only forensic audit script — SPA provider
 * DO NOT MODIFY ANYTHING — queries only
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "\n===========================================================\n";
echo "SECTION 1 — PROVIDERS\n";
echo "===========================================================\n";
$providers = DB::table('providers')
    ->select('id','display_name','type','slug')
    ->orderBy('created_at')
    ->get();
foreach ($providers as $p) {
    echo "  id={$p->id} | name={$p->display_name} | type={$p->type} | slug={$p->slug}\n";
}

echo "\n===========================================================\n";
echo "SECTION 2 — LOCATIONS (all providers)\n";
echo "===========================================================\n";
$locations = DB::table('locations')
    ->select('id','provider_id','name','address','is_primary','is_active as active')
    ->orderBy('provider_id')
    ->get();
foreach ($locations as $l) {
    echo "  id={$l->id} | provider={$l->provider_id} | name={$l->name} | primary=" . ($l->is_primary ? 'YES' : 'no') . " | active=" . ($l->active ? 'YES' : 'no') . "\n";
}

echo "\n===========================================================\n";
echo "SECTION 3 — ALL STAFF RECORDS\n";
echo "===========================================================\n";
$staff = DB::table('staff')
    ->select('id','provider_id','location_id','name','email','phone','user_id','active')
    ->orderBy('provider_id')
    ->get();
echo "Total staff rows: " . count($staff) . "\n";
foreach ($staff as $s) {
    echo "  id={$s->id}\n";
    echo "    name={$s->name} | active=" . ($s->active ? 'YES' : 'no') . "\n";
    echo "    provider_id={$s->provider_id}\n";
    echo "    location_id=" . ($s->location_id ?? 'NULL') . "\n";
    echo "    user_id=" . ($s->user_id ?? 'NULL') . "\n";
    echo "    email=" . ($s->email ?? 'NULL') . " | phone=" . ($s->phone ?? 'NULL') . "\n";
}

echo "\n===========================================================\n";
echo "SECTION 4 — ALL SPECIALIST RECORDS\n";
echo "===========================================================\n";
$specialists = DB::select('SELECT id, provider_id, name, active FROM specialists ORDER BY provider_id');
echo "Total specialist rows: " . count($specialists) . "\n";
foreach ($specialists as $s) {
    echo "  id={$s->id}\n";
    echo "    name={$s->name} | active=" . ($s->active ? 'YES' : 'no') . "\n";
    echo "    provider_id=" . ($s->provider_id ?? 'NULL') . "\n";
}

echo "\n===========================================================\n";
echo "SECTION 5 — ALL SPECIALIST_ASSIGNMENTS\n";
echo "===========================================================\n";
$assignments = DB::table('specialist_assignments as sa')
    ->leftJoin('specialists as sp', 'sp.id', '=', 'sa.specialist_id')
    ->leftJoin('staff as st', 'st.id', '=', 'sa.staff_id')
    ->leftJoin('providers as p', 'p.id', '=', 'sa.provider_id')
    ->select(
        'sa.id', 'sa.provider_id', 'sa.location_id', 'sa.specialist_id',
        'sa.staff_id', 'sa.status', 'sa.is_primary', 'sa.employment_type',
        'sp.name as specialist_name', 'sp.provider_id as specialist_provider_id',
        'st.name as staff_name', 'st.provider_id as staff_provider_id',
        'p.display_name as provider_name', 'p.type as provider_type'
    )
    ->orderBy('sa.provider_id')
    ->get();
echo "Total assignment rows: " . count($assignments) . "\n";
foreach ($assignments as $a) {
    echo "  assignment.id={$a->id}\n";
    echo "    provider_id={$a->provider_id} ({$a->provider_name} / type={$a->provider_type})\n";
    echo "    location_id=" . ($a->location_id ?? 'NULL') . "\n";
    echo "    specialist_id={$a->specialist_id} ({$a->specialist_name}) own_provider={$a->specialist_provider_id}\n";
    echo "    staff_id=" . ($a->staff_id ?? 'NULL') . " staff_name=" . ($a->staff_name ?? 'NULL') . " staff_provider=" . ($a->staff_provider_id ?? 'NULL') . "\n";
    echo "    status={$a->status} | is_primary=" . ($a->is_primary ? 'YES' : 'no') . " | employment={$a->employment_type}\n";
}

echo "\n===========================================================\n";
echo "SECTION 6 — USERS with provider_users rows\n";
echo "===========================================================\n";
$pusers = DB::table('provider_users as pu')
    ->leftJoin('users as u', 'u.id', '=', 'pu.user_id')
    ->leftJoin('providers as p', 'p.id', '=', 'pu.provider_id')
    ->select('pu.id','pu.user_id','pu.provider_id','pu.role','pu.authority_scope',
             'u.name as user_name','u.email as user_email',
             'p.display_name as provider_name')
    ->orderBy('pu.provider_id')
    ->get();
echo "Total provider_user rows: " . count($pusers) . "\n";
foreach ($pusers as $pu) {
    echo "  user_id={$pu->user_id} ({$pu->user_name}) | provider={$pu->provider_id} ({$pu->provider_name}) | role={$pu->role} | scope={$pu->authority_scope}\n";
}

echo "\n===========================================================\n";
echo "SECTION 7 — SEARCH FOR MARKO specifically\n";
echo "===========================================================\n";
$marko_staff = DB::table('staff')->where('name', 'like', '%Marko%')->orWhere('name', 'like', '%marko%')->get();
$marko_spec  = DB::table('specialists')->where('name', 'like', '%Marko%')->orWhere('name', 'like', '%marko%')->get();
$marko_users = DB::table('users')->where('name', 'like', '%Marko%')->orWhere('name', 'like', '%marko%')->get();

echo "Marko as Staff records: " . count($marko_staff) . "\n";
foreach ($marko_staff as $m) {
    echo "  staff.id={$m->id} | provider_id={$m->provider_id} | location_id=" . ($m->location_id ?? 'NULL') . " | active=" . ($m->active ? 'YES' : 'no') . "\n";
}
echo "Marko as Specialist records: " . count($marko_spec) . "\n";
foreach ($marko_spec as $m) {
    echo "  specialist.id={$m->id} | provider_id=" . ($m->provider_id ?? 'NULL') . " | role=" . ($m->role ?? 'NULL') . " | active=" . ($m->active ? 'YES' : 'no') . "\n";
}
echo "Marko as User records: " . count($marko_users) . "\n";
foreach ($marko_users as $m) {
    echo "  user.id={$m->id} | email={$m->email} | name={$m->name}\n";
}

// Marko's assignments
if (count($marko_spec) > 0) {
    foreach ($marko_spec as $m) {
        $marko_assignments = DB::table('specialist_assignments as sa')
            ->leftJoin('providers as p', 'p.id', '=', 'sa.provider_id')
            ->where('sa.specialist_id', $m->id)
            ->select('sa.*','p.display_name as pname','p.type as ptype')
            ->get();
        echo "Marko ({$m->name}) assignments: " . count($marko_assignments) . "\n";
        foreach ($marko_assignments as $a) {
            echo "  assign.id={$a->id} | provider_id={$a->provider_id} ({$a->pname} type={$a->ptype}) | status={$a->status} | location_id=" . ($a->location_id ?? 'NULL') . " | staff_id=" . ($a->staff_id ?? 'NULL') . " | is_primary=" . ($a->is_primary ? 'YES' : 'no') . "\n";
        }
    }
}

echo "\n===========================================================\n";
echo "SECTION 8 — AssignStaff-relevant: specialists seen in bookings for SPA\n";
echo "===========================================================\n";
// Find what specialist_ids appear in bookings for the SPA provider
$bookingSpecs = DB::table('bookings as b')
    ->leftJoin('specialists as sp', 'sp.id', '=', 'b.specialist_id')
    ->leftJoin('providers as p', 'p.id', '=', 'b.provider_id')
    ->whereNotNull('b.specialist_id')
    ->select('b.provider_id','p.display_name as pname','b.specialist_id','sp.name as spec_name','sp.provider_id as spec_own_provider')
    ->groupBy('b.provider_id','p.display_name','b.specialist_id','sp.name','sp.provider_id')
    ->get();
echo "Specialist-booking pairs:\n";
foreach ($bookingSpecs as $bs) {
    echo "  booking_provider={$bs->provider_id} ({$bs->pname}) | specialist={$bs->specialist_id} ({$bs->spec_name}) | specialist_owns_provider={$bs->spec_own_provider}\n";
}

echo "\n===========================================================\n";
echo "SECTION 9 — PROVIDER_USER_LOCATIONS (location authority)\n";
echo "===========================================================\n";
$pul = DB::table('provider_user_locations')->get();
echo "Total provider_user_locations rows: " . count($pul) . "\n";
foreach ($pul as $r) {
    echo "  provider_user_id={$r->provider_user_id} | location_id={$r->location_id}\n";
}

echo "\n===========================================================\n";
echo "SECTION 10 — salon_specialist legacy pivot (if exists)\n";
echo "===========================================================\n";
try {
    $ss = DB::table('salon_specialist')->get();
    echo "salon_specialist rows: " . count($ss) . "\n";
    foreach ($ss as $r) {
        echo "  specialist_id={$r->specialist_id} | salon_id=" . ($r->salon_id ?? 'NULL') . " | active=" . ($r->active ?? 'NULL') . "\n";
    }
} catch (\Exception $e) {
    echo "salon_specialist table: " . $e->getMessage() . "\n";
}

echo "\n===========================================================\n";
echo "DONE\n";
echo "===========================================================\n";
