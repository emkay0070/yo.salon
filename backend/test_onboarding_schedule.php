<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\OnboardingService;
use App\Services\MediaService;

echo "=== Testing Onboarding Schedule Persistence ===\n\n";

// Create a test user
$testEmail = 'test-schedule-' . time() . '@example.com';
$user = User::where('email', $testEmail)->first();

if (!$user) {
    $user = User::create([
        'email' => $testEmail,
        'password' => bcrypt('password'),
        'name' => 'Test User',
        'status' => 'pending',
    ]);
    echo "Created test user: {$user->email}\n";
} else {
    echo "Using existing test user: {$user->email}\n";
}

// Simulate onboarding data with schedule
$onboardingData = [
    'business' => [
        'name' => 'Test Salon Schedule ' . time(),
        'category' => 'hair_salon',
        'phone' => '+256700000000',
        'email' => $testEmail,
        'address' => 'Test Address',
        'city' => 'Kampala',
        'country' => 'Uganda',
        'timezone' => 'Africa/Kampala',
    ],
    'wallet' => [
        'openingHours' => [
            'monday' => ['open' => '09:00', 'close' => '18:00'],
            'tuesday' => ['open' => '09:00', 'close' => '18:00'],
            'wednesday' => ['open' => '09:00', 'close' => '18:00'],
            'thursday' => ['open' => '09:00', 'close' => '18:00'],
            'friday' => ['open' => '09:00', 'close' => '16:00'],
            'saturday' => ['open' => '09:00', 'close' => '18:00'],
            'sunday' => ['open' => '09:00', 'close' => '18:00'],
        ],
        'appointmentDuration' => 30,
        'paymentMethods' => ['cash'],
    ],
];

echo "\nRunning onboarding...\n";

try {
    $onboardingService = new OnboardingService(app(MediaService::class));
    $provider = $onboardingService->complete($user, $onboardingData);

    echo "Onboarding completed successfully!\n";
    echo "Provider ID: {$provider->id}\n";
    echo "Provider Name: {$provider->display_name}\n";
    echo "Provider Type: {$provider->type}\n\n";

    // Check LocationSchedule records
    $location = $provider->locations()->first();
    if ($location) {
        echo "Location ID: {$location->id}\n";
        echo "Location Name: {$location->name}\n\n";

        $locationSchedules = \App\Models\LocationSchedule::where('location_id', $location->id)->get();
        echo "LocationSchedule records created: {$locationSchedules->count()}\n";

        foreach ($locationSchedules as $ls) {
            $status = $ls->is_closed ? 'CLOSED' : 'OPEN';
            echo "  {$ls->day_of_week}: {$status} ({$ls->open_time} - {$ls->close_time})\n";
        }
    } else {
        echo "No location created\n";
    }

    // Test availability
    echo "\n=== Testing Availability ===\n";
    $availabilityEngine = app(\App\Services\AvailabilityEngine::class);
    $availability = $availabilityEngine->getAvailableSlots(
        $provider->id,
        $location?->id,
        date('Y-m-d'),
        null,
        null,
        15
    );

    echo "Domain Status: {$availability->status->value}\n";
    echo "Total Available Slots: {$availability->totalAvailableSlots}\n";
    echo "Operating Hours: " . json_encode($availability->operatingHours) . "\n";

    if ($availability->totalAvailableSlots > 0) {
        echo "\nFirst 5 slots:\n";
        foreach ($availability->slots->take(5) as $slot) {
            echo "  {$slot['start']} - {$slot['end']}\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
