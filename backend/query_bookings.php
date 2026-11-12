<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Recent Bookings ===\n";
$bookings = \App\Models\Booking::orderBy('created_at', 'desc')->limit(5)->get(['id', 'customer_id', 'provider_id', 'specialist_id', 'created_at', 'status']);
foreach ($bookings as $booking) {
    echo "ID: {$booking->id}, Customer: {$booking->customer_id}, Provider: {$booking->provider_id}, Specialist: {$booking->specialist_id}, Created: {$booking->created_at}, Status: {$booking->status}\n";
}

echo "\n=== CustomerSpecialist Records ===\n";
$relationships = \App\Models\CustomerSpecialist::orderBy('created_at', 'desc')->limit(5)->get();
foreach ($relationships as $rel) {
    echo "Customer: {$rel->customer_id}, Specialist: {$rel->specialist_id}, Provider: {$rel->provider_id}, Total Bookings: {$rel->total_bookings}, Created: {$rel->created_at}\n";
}

echo "\n=== Customer_Providers Records ===\n";
$cp = \DB::table('customer_providers')->orderBy('created_at', 'desc')->limit(5)->get();
foreach ($cp as $row) {
    echo "Customer: {$row->customer_id}, Provider: {$row->provider_id}, Visits: {$row->visits}, Created: {$row->created_at}\n";
}
