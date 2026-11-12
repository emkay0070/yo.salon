<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\BookingOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Get command line arguments
$processNum = $argv[1] ?? '1';
$specialistId = $argv[2] ?? null;
$testDate = $argv[3] ?? null;
$testTime = $argv[4] ?? null;

// Generate unique time if not provided (to avoid conflicts with existing bookings)
if (!$testTime) {
    $hour = (int)date('H');
    $minute = ((int)date('i') + 10) % 60;
    $testTime = sprintf('%02d:%02d', $hour, $minute);
}

// Handle "skip" or empty specialistId
if (!$specialistId || $specialistId === 'skip') {
    $specialistId = null;
}

// Barrier synchronization - wait for both processes to be ready
$barrierFile = __DIR__ . '/concurrent_barrier.txt';
file_put_contents($barrierFile, ($processNum) . PHP_EOL, FILE_APPEND);

// Wait until both processes have written to the barrier file
while (substr_count(file_get_contents($barrierFile), PHP_EOL) < 2) {
    usleep(10000); // 10ms
}

// Both processes are now ready - proceed simultaneously without random delay
// The race condition will occur naturally due to OS scheduling

// Create fresh database connection for this process
DB::purge('pgsql');
DB::reconnect('pgsql');

try {
    // Find or create test data
    $provider = Provider::first();
    if (!$provider) {
        echo "Error: No provider found in database\n";
        exit(1);
    }

    $location = Location::where('provider_id', $provider->id)->first();
    if (!$location) {
        echo "Error: No location found\n";
        exit(1);
    }

    $specialist = $specialistId ? Specialist::find($specialistId) : Specialist::first();
    if (!$specialist) {
        echo "Error: No specialist found\n";
        exit(1);
    }

    $service = Service::where('provider_id', $provider->id)->first();
    if (!$service) {
        echo "Error: No service found\n";
        exit(1);
    }

    $customer = Customer::first();
    if (!$customer) {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $provider->id,
            'name' => "Test Customer {$processNum}",
            'email' => "test{$processNum}@example.com",
            'phone' => '1234567890',
        ]);
    }

    // Create booking data
    $bookingData = [
        'provider_id' => $provider->id,
        'location_id' => $location->id,
        'service_id' => $service->id,
        'specialist_id' => $specialist->id,
        'customer_id' => $customer->id,
        'date' => $testDate ?? CarbonImmutable::now()->addDays(1)->toDateString(),
        'time' => $testTime,
        'customer_name' => "Test Customer {$processNum}",
        'customer_phone' => '1234567890',
        'customer_email' => "test{$processNum}@example.com",
        'internal_booking' => true, // Bypass availability validation
    ];

    // Attempt booking
    $orchestrator = new \App\Services\BookingOrchestrator(
        app(\App\Domain\Availability\Engines\AvailabilityEngine::class),
        app(\App\Services\BookingService::class),
        app(\App\Services\ResourceAllocator::class),
        app(\App\Services\BookingConflictService::class)
    );

    $result = $orchestrator->reserveAndBook($bookingData);

    // Success
    echo "SUCCESS:{$result['booking']->id}\n";
    exit(0);

} catch (\Exception $e) {
    // Failure
    echo "FAILED:" . $e->getMessage() . "\n";
    exit(1);
}
