<?php
$file = 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/MembershipController.php';
$content = file_get_contents($file);
$content = str_replace("attributes->get('salon_id')", "attributes->get('provider_id')", $content);
$content = str_replace('getSubscriptionBySalon', 'getSubscriptionByProvider', $content);
$content = str_replace('$salonId', '$providerId', $content);
file_put_contents($file, $content);
echo "Done";
