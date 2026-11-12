<?php

$files = [
    'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Notifications/WelcomeEmail.php',
    'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Notifications/BookingReminder.php',
    'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Notifications/BookingConfirmed.php',
    'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Models/BrandExperience.php',
    'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/ScheduleExceptionController.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // General replacements
        $content = str_replace('$salon', '$provider', $content);
        $content = str_replace('Salon $provider', 'Provider $provider', $content);
        $content = str_replace('$this->salon', '$this->provider', $content);
        $content = str_replace('$provider->name', '$provider->display_name', $content); // Provider model uses display_name not name
        $content = str_replace('use App\Models\Salon;', 'use App\Models\Provider;', $content);
        
        file_put_contents($file, $content);
    }
}

echo "Notifications and other remaining files updated.\n";
