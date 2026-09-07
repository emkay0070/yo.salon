<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$goals = \DB::table('specialist_goals')->get();

echo "Specialist Goals in database:\n";
echo "=============================\n";

foreach ($goals as $goal) {
    echo "ID: {$goal->id}\n";
    echo "Specialist ID: {$goal->specialist_id}\n";
    echo "Goal Type: {$goal->goal_type}\n";
    echo "Target: {$goal->target_value}\n";
    echo "Metric: {$goal->metric}\n";
    echo "Status: {$goal->status}\n";
    echo "-------------------\n";
}

if ($goals->isEmpty()) {
    echo "No goals found in specialist_goals table.\n";
}
