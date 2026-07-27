<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$salon = App\Models\Salon::first();
$engine = app(App\Services\Intelligence\IntelligenceEngine::class);
echo json_encode($engine->generate($salon), JSON_PRETTY_PRINT);
