<?php

$replacements = [
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/SpecialistController.php',
        'search' => '$specialist->salons',
        'replace' => '$specialist->providers'
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/SpecialistPortalController.php',
        'search' => '$specialist->salons()',
        'replace' => '$specialist->providers()'
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/UserController.php',
        'search' => '$user->salons',
        'replace' => '$user->providers'
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/UserController.php',
        'search' => "'salons_count'",
        'replace' => "'providers_count'"
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/UserController.php',
        'search' => "'first_salon'",
        'replace' => "'first_provider'"
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Http/Controllers/Api/V1/SpecialistDiscoveryController.php',
        'search' => '$specialist->salons',
        'replace' => '$specialist->providers'
    ],
    [
        'file' => 'e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Services/OnboardingService.php',
        'search' => '$user->salons()',
        'replace' => '$user->providers()'
    ]
];

foreach ($replacements as $rep) {
    if (file_exists($rep['file'])) {
        $content = file_get_contents($rep['file']);
        $content = str_replace($rep['search'], $rep['replace'], $content);
        file_put_contents($rep['file'], $content);
    }
}

echo "Controllers and services updated to use providers instead of salons.";
