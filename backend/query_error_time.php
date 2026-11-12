<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Bookings around 2026-10-08 19:30:13 ===\n";
$bookings = \App\Models\Booking::where('created_at', '>=', '2026-10-08 19:00:00')
    ->where('created_at', '<=', '2026-10-08 20:00:00')
    ->orderBy('created_at', 'desc')
    ->get(['id', 'customer_id', 'provider_id', 'specialist_id', 'created_at', 'status']);

echo "Found {$bookings->count()} bookings\n";
foreach ($bookings as $booking) {
    echo "ID: {$booking->id}, Customer: {$booking->customer_id}, Provider: {$booking->provider_id}, Specialist: {$booking->specialist_id}, Created: {$booking->created_at}, Status: {$booking->status}\n";
}

echo "\n=== CustomerSpecialist records around 2026-10-08 19:30:13 ===\n";
$relationships = \App\Models\CustomerSpecialist::where('created_at', '>=', '2026-10-08 19:00:00')
    ->where('created_at', '<=', '2026-10-08 20:00:00')
    ->orderBy('created_at', 'desc')
    ->get();

echo "Found {$relationships->count()} relationships\n";
foreach ($relationships as $rel) {
    echo "Customer: {$rel->customer_id}, Specialist: {$rel->specialist_id}, Provider: {$rel->provider_id}, Total Bookings: {$rel->total_bookings}, Created: {$rel->created_at}\n";
}

echo "\n=== Customer_Providers records around 2026-10-08 19:30:13 ===\n";
$cp = \DB::table('customer_providers')
    ->where('created_at', '>=', '2026-10-08 19:00:00')
    ->where('created_at', '<=', '2026-10-08 20:00:00')
    ->orderBy('created_at', 'desc')
    ->get();

echo "Found {$cp->count()} customer_providers\n";
foreach ($cp as $row) {
    echo "Customer: {$row->customer_id}, Provider: {$row->provider_id}, Visits: {$row->visits}, Created: {$row->created_at}\n";
}
