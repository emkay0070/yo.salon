<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Provider;

$provider = Provider::first();
if ($provider) {
    echo $provider->id;
} else {
    echo "";
}
