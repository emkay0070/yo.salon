<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== salon_id and customer_salon_id columns ===\n";
$results = DB::select("SELECT table_name, column_name FROM information_schema.columns WHERE column_name IN ('salon_id', 'customer_salon_id') ORDER BY table_name, column_name");
if (empty($results)) {
    echo "NO COLUMNS FOUND\n";
} else {
    foreach ($results as $row) {
        echo $row->table_name . " | " . $row->column_name . "\n";
    }
}

echo "\n=== Table existence check ===\n";
$tables = ['salons', 'customer_salons', 'salon_specialist', 'customer_providers', 'specialist_assignments'];
foreach ($tables as $table) {
    $exists = DB::select("SELECT table_name FROM information_schema.tables WHERE table_name = ?", [$table]);
    echo $table . " | " . (count($exists) > 0 ? "EXISTS" : "DOES NOT EXIST") . "\n";
}
