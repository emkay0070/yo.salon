<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== USERS ===" . PHP_EOL;
foreach (\App\Models\User::all() as $u) {
    echo "  [" . $u->id . "] " . $u->name . " | status: " . $u->status . PHP_EOL;
}

echo PHP_EOL . "=== SALONS ===" . PHP_EOL;
foreach (\App\Models\Salon::all() as $s) {
    echo "  [" . $s->id . "] " . $s->name . PHP_EOL;
}

echo PHP_EOL . "=== BOOKINGS ===" . PHP_EOL;
$bookings = \App\Models\Booking::withoutGlobalScopes()->with(['customer', 'service'])->get();
echo "Total: " . $bookings->count() . PHP_EOL;
foreach ($bookings as $b) {
    echo "  [salon:" . $b->salon_id . "] " . ($b->customer->name ?? 'no-customer') . " | " . ($b->service->name ?? 'no-service') . " | date: " . $b->date . PHP_EOL;
}

echo PHP_EOL . "=== CUSTOMERS ===" . PHP_EOL;
$customers = \App\Models\Customer::withoutGlobalScopes()->get();
echo "Total: " . $customers->count() . PHP_EOL;
foreach ($customers as $c) {
    echo "  [salon:" . $c->salon_id . "] " . $c->name . PHP_EOL;
}
