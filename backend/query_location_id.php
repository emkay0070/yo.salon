<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Location;

$providerId = $argv[1] ?? null;
if (!$providerId) {
    echo "";
    exit(0);
}

$location = Location::where('provider_id', $providerId)->first();
if ($location) {
    echo $location->id;
} else {
    echo "";
}
