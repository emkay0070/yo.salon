<?php

$file = 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Services/SubscriptionService.php';
$content = file_get_contents($file);

$content = str_replace('$salon = $subscription->salon;', '$provider = $subscription->provider;', $content);
$content = str_replace('if ($salon) {', 'if ($provider) {', $content);
$content = str_replace(', $salon->id);', ', $provider->id);', $content);
$content = str_replace('string $salonId', 'string $providerId', $content);
$content = str_replace('$salonId,', '$providerId,', $content);
$content = str_replace('usage($salonId,', 'usage($providerId,', $content);
$content = str_replace('@param string $salonId - The salon ID', '@param string $providerId - The provider ID', $content);

file_put_contents($file, $content);
echo "SubscriptionService updated.\n";
