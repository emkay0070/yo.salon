<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use Carbon\CarbonImmutable;

// Delete bookings for tomorrow at the test time
$testDate = CarbonImmutable::now()->addDays(1)->toDateString();
$testTime = $argv[1] ?? '16:45';

$deleted = Booking::where('date', $testDate)
    ->where('time', $testTime)
    ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
    ->delete();

echo "Deleted $deleted booking(s) for $testDate $testTime\n";
