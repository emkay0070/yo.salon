<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$goals = \App\Models\JourneyGoal::with('specialist')->get();

echo "Journey Goals in database:\n";
echo "=========================\n";

foreach ($goals as $goal) {
    echo "ID: {$goal->id}\n";
    echo "Title: {$goal->title}\n";
    echo "Specialist: {$goal->specialist->name}\n";
    echo "Target: {$goal->target_value}\n";
    echo "Current: {$goal->current_value}\n";
    echo "Status: {$goal->status}\n";
    echo "Deadline: {$goal->deadline}\n";
    echo "-------------------\n";
}

if ($goals->isEmpty()) {
    echo "No goals found in journey_goals table.\n";
}
