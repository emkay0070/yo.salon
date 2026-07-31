<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pa = \App\Models\PortalAccount::where('email', 'emkay@gmail.com')->first();

if ($pa) {
    echo "PortalAccount ID: " . $pa->id . "\n";
    echo "Customer ID: " . $pa->customer_id . "\n";
    
    $c = \App\Models\Customer::find($pa->customer_id);
    
    if ($c) {
        echo "Customer Name: " . $c->name . "\n";
        echo "Customer Email: " . $c->email . "\n";
        echo "Customer Phone: " . $c->phone . "\n";
    } else {
        echo "Customer NOT found in customers table\n";
    }
} else {
    echo "PortalAccount not found\n";
}
