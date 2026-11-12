<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\Specialist;

$specialistId = $argv[1] ?? null;
$testDate = $argv[2] ?? null;
$testTime = $argv[3] ?? null;

if (!$specialistId || !$testDate || !$testTime) {
    echo "Error: Missing arguments\n";
    exit(1);
}

$count = Booking::where('specialist_id', $specialistId)
    ->where('date', $testDate)
    ->where('time', $testTime)
    ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
    ->count();

echo $count;
