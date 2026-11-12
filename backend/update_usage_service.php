<?php

$file = 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Services/UsageService.php';
$content = file_get_contents($file);

// Replace $salon = $subscription->salon; with $provider = $subscription->provider;
$content = str_replace('$salon = $subscription->salon;', '$provider = $subscription->provider;', $content);
$content = str_replace('Salon not found', 'Provider not found', $content);
$content = str_replace('$salon->id', '$provider->id', $content);
$content = str_replace('if (!$salon)', 'if (!$provider)', $content);
$content = str_replace('$salon->staff()->count()', '$provider->assignments()->count()', $content);

file_put_contents($file, $content);
echo "UsageService updated.\n";
