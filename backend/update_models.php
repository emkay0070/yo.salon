<?php
$models = [
    'ServicePackage',
    'Profile',
    'FeatureFlag',
    'CustomerTimeline',
    'CustomerPreference',
    'CustomerPackage',
    'CustomerFavoriteService',
    'CustomerFavorite',
    'CustomerCollection',
    'CustomerActivity',
    'BrandExperience'
];

foreach ($models as $model) {
    $file = "e:/Year 2026/Feb/codding/websites/yo salon/backend/app/Models/{$model}.php";
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Replace public function salon()
    // It might be formatted differently, so use regex
    $content = preg_replace(
        '/public function salon\(\).*?\{.*?return \$this->belongsTo\(Salon::class\);.*?}/s',
        "public function provider()\n    {\n        return \$this->belongsTo(Provider::class, 'salon_id');\n    }",
        $content
    );
    
    // Also if it has : BelongsTo
    $content = preg_replace(
        '/public function salon\(\): BelongsTo.*?\{.*?return \$this->belongsTo\(Salon::class\);.*?}/s',
        "public function provider(): BelongsTo\n    {\n        return \$this->belongsTo(Provider::class, 'salon_id');\n    }",
        $content
    );

    file_put_contents($file, $content);
}
echo "Done replacing relationships.\n";
