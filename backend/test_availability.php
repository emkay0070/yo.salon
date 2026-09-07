<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$engine = app(\App\Domain\Availability\Engines\AvailabilityEngine::class);
$date = '2026-08-05';
$salonId = '019fcbfa-c4cb-7121-a6cf-9686fc300e1a'; // from previous context
// Let's get the assignment ID for the specialist.
$specialistId = '019fcbfa-c6b2-7045-83f0-63591b9a4b8f';
$assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)->first();

if (!$assignment) {
    echo "Assignment not found\n";
    exit;
}
$assignmentId = $assignment->id;

// We will replicate what happens in isSlotAvailable
$contextQuery = app(\App\Domain\Availability\Queries\AvailabilityContextBuilder::class);
$generator = app(\App\Domain\Availability\Engines\AvailabilityWindowGenerator::class);

$targetDate = \Carbon\CarbonImmutable::parse($date);
$context = $contextQuery->build($assignmentId, $targetDate);
$availableSlots = $generator->generate($context);

echo "Available Slots for {$date}:\n";
foreach ($availableSlots as $slot) {
    echo "- {$slot->startsAt} to {$slot->endsAt} ({$slot->status})\n";
}

$startTime = '09:45:00';
$durationMinutes = 30;

$requestedStart = \Carbon\CarbonImmutable::parse("$date $startTime");
$requestedEnd = $requestedStart->addMinutes($durationMinutes);

echo "\nRequested: {$requestedStart->format('Y-m-d H:i:s')} to {$requestedEnd->format('Y-m-d H:i:s')}\n";

$isAvailable = $engine->isSlotAvailable($assignmentId, $date, $startTime, $durationMinutes);
echo "\nisSlotAvailable result: " . ($isAvailable ? 'true' : 'false') . "\n";
