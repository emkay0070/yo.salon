<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s = \App\Models\OnboardingSession::latest()->first(); 
echo json_encode(['completed' => $s->completed, 'user_id' => $s->user_id]);
if ($s->completed) {
    // Revert the completed status so the user can try again!
    $s->update(['completed' => false, 'current_step' => 'membership']);
    echo " REVERTED!";
    
    // We should also delete the salon that was created, so it doesn't create a duplicate!
    $salon = \App\Models\Salon::where('slug', 'like', '%')->latest()->first();
    if ($salon) {
        $salon->delete();
        echo " DELETED SALON " . $salon->id;
    }
}
