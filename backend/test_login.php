<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'emmak01@gmail.com')->first();
if (!$user) {
    echo "User not found.\n";
    exit(1);
}

echo "User found. ID: " . $user->id . "\n";
$check = \Illuminate\Support\Facades\Hash::check('password123', $user->password);

echo "Hash check for 'password123': " . ($check ? 'SUCCESS' : 'FAILED') . "\n";
