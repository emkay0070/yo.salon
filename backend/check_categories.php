<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$categories = DB::table('business_categories')->get(['slug', 'provider_type']);

echo "Business Categories:\n";
foreach($categories as $c) {
    echo "  {$c->slug} -> {$c->provider_type}\n";
}
