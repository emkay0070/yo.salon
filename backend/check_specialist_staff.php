<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$specialist = App\Models\Specialist::first();
if ($specialist) {
    $staff = App\Models\Staff::find($specialist->id);
    echo json_encode([
        "specialist_id" => $specialist->id,
        "staff_id" => $staff ? $staff->id : null,
        "match" => $staff && $staff->id === $specialist->id
    ]);
} else {
    echo json_encode(["error" => "No specialists found"]);
}
