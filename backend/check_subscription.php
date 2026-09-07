<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$salon = App\Models\Salon::where('slug', 'em-cuts')->with('provider.subscription.plan')->first();

if ($salon) {
    echo "=== Salon Found ===\n";
    echo "Name: " . $salon->name . "\n";
    echo "Slug: " . $salon->slug . "\n";
    echo "ID: " . $salon->id . "\n\n";
    
    if ($salon->provider) {
        echo "=== Provider ===\n";
        echo "ID: " . $salon->provider->id . "\n";
        echo "Name: " . $salon->provider->name . "\n\n";
        
        if ($salon->provider->subscription) {
            echo "=== Subscription ===\n";
            echo "ID: " . $salon->provider->subscription->id . "\n";
            echo "Status: " . $salon->provider->subscription->status . "\n";
            echo "Billing Cycle: " . $salon->provider->subscription->billing_cycle . "\n";
            echo "Trial Ends At: " . ($salon->provider->subscription->trial_ends_at ? $salon->provider->subscription->trial_ends_at->toDateTimeString() : 'N/A') . "\n";
            echo "Starts At: " . ($salon->provider->subscription->starts_at ? $salon->provider->subscription->starts_at->toDateTimeString() : 'N/A') . "\n";
            echo "Renews At: " . ($salon->provider->subscription->renews_at ? $salon->provider->subscription->renews_at->toDateTimeString() : 'N/A') . "\n\n";
            
            if ($salon->provider->subscription->plan) {
                echo "=== Plan ===\n";
                echo "Name: " . $salon->provider->subscription->plan->name . "\n";
                echo "Slug: " . $salon->provider->subscription->plan->slug . "\n";
                echo "Staff Limit: " . $salon->provider->subscription->plan->staff_limit . "\n";
                echo "Branches Limit: " . $salon->provider->subscription->plan->branches_limit . "\n";
                echo "Storage Limit: " . $salon->provider->subscription->plan->storage_limit_gb . " GB\n";
            } else {
                echo "No plan associated with subscription\n";
            }
        } else {
            echo "No subscription found for provider\n";
        }
    } else {
        echo "No provider associated with salon\n";
    }
    
    // Count staff members
    $staffCount = App\Models\SpecialistAssignment::where('salon_id', $salon->id)->count();
    echo "\n=== Staff Count ===\n";
    echo "Total staff members: " . $staffCount . "\n";
    
    // Show staff details
    $staff = App\Models\SpecialistAssignment::where('salon_id', $salon->id)->with('specialist')->get();
    echo "\n=== Staff Details ===\n";
    foreach ($staff as $assignment) {
        echo "- " . ($assignment->specialist ? $assignment->specialist->name : 'Unknown') . " (ID: " . $assignment->specialist_id . ")\n";
    }
    
} else {
    echo "Salon with slug 'em-cuts' not found\n";
}
