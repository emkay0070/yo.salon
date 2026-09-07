<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$specialistId = '019ff1da-4cb4-730e-82bb-cf16e192a45f';
$assignments = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)->get();

echo "Count: " . $assignments->count() . "\n";
foreach ($assignments as $a) {
    echo "- ID: {$a->id}, Provider ID: {$a->provider_id}, Status: {$a->status}, Role: {$a->role}\n";
}
