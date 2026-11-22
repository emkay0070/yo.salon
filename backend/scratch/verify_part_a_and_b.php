<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use App\Models\SpecialistAccount;
use App\Models\PortalAccount;
use App\Services\PlatformAuthorizationService;
use Illuminate\Support\Facades\Route;

$authService = $app->make(PlatformAuthorizationService::class);

$passed = 0;
$failed = 0;

function assertCondition($condition, $description) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $description\n";
        $passed++;
    } else {
        echo "  [FAIL] $description\n";
        $failed++;
    }
}

echo "\n=== PART A: Email-Based Platform Role Resolution & Verification Security ===\n";

// Configure test platform roles
config([
    'platform.roles' => [
        PlatformRole::PLATFORM_OWNER => ['owner@tybuel.io'],
        PlatformRole::PLATFORM_SUPER_ADMIN => ['superadmin@tybuel.io'],
        PlatformRole::DEVELOPER => ['dev@tybuel.io'],
        PlatformRole::SUPPORT => ['support@tybuel.io'],
    ]
]);

// 1. Unverified account matching configured email
$unverifiedOwner = new User([
    'name' => 'Unverified Owner',
    'email' => 'owner@tybuel.io',
    'status' => 'registered',
]);
$unverifiedOwner->email_verified_at = null;

assertCondition(!$unverifiedOwner->hasVerifiedEmail(), "Unverified owner hasVerifiedEmail() returns false");
assertCondition($authService->resolvePlatformRole($unverifiedOwner) === null, "Unverified owner resolves to NULL platform role");
assertCondition(!$authService->hasPlatformRole($unverifiedOwner, PlatformRole::PLATFORM_OWNER), "Unverified owner hasPlatformRole() returns false");
assertCondition(!$authService->canPerform($unverifiedOwner, 'seeder_execution'), "Unverified owner cannot perform seeder_execution");
assertCondition(!$authService->canPerform($unverifiedOwner, 'invoices_view'), "Unverified owner cannot perform invoices_view");

// 2. Verified account matching configured email
$verifiedOwner = new User([
    'name' => 'Verified Owner',
    'email' => 'owner@tybuel.io',
    'status' => 'active',
]);
$verifiedOwner->email_verified_at = now();

assertCondition($verifiedOwner->hasVerifiedEmail(), "Verified owner hasVerifiedEmail() returns true");
assertCondition($authService->resolvePlatformRole($verifiedOwner) === PlatformRole::PLATFORM_OWNER, "Verified owner resolves to platform_owner");
assertCondition($authService->hasPlatformRole($verifiedOwner, PlatformRole::PLATFORM_OWNER), "Verified owner hasPlatformRole() returns true");
assertCondition($authService->canPerform($verifiedOwner, 'seeder_execution'), "Verified owner can perform seeder_execution");

// 3. Super Admin, Developer, Support with verified emails
$verifiedSuper = new User(['email' => 'superadmin@tybuel.io']);
$verifiedSuper->email_verified_at = now();
assertCondition($authService->resolvePlatformRole($verifiedSuper) === PlatformRole::PLATFORM_SUPER_ADMIN, "Verified Super Admin resolves correctly");

$unverifiedSuper = new User(['email' => 'superadmin@tybuel.io']);
$unverifiedSuper->email_verified_at = null;
assertCondition($authService->resolvePlatformRole($unverifiedSuper) === null, "Unverified Super Admin is strictly denied");

$verifiedDev = new User(['email' => 'dev@tybuel.io']);
$verifiedDev->email_verified_at = now();
assertCondition($authService->resolvePlatformRole($verifiedDev) === PlatformRole::DEVELOPER, "Verified Developer resolves correctly");
assertCondition($authService->canPerform($verifiedDev, 'seeder_view'), "Verified Developer can view seeders");
assertCondition(!$authService->canPerform($verifiedDev, 'seeder_execution'), "Verified Developer CANNOT execute seeders");

$unverifiedDev = new User(['email' => 'dev@tybuel.io']);
$unverifiedDev->email_verified_at = null;
assertCondition($authService->resolvePlatformRole($unverifiedDev) === null, "Unverified Developer is strictly denied");

$verifiedSupport = new User(['email' => 'support@tybuel.io']);
$verifiedSupport->email_verified_at = now();
assertCondition($authService->resolvePlatformRole($verifiedSupport) === PlatformRole::SUPPORT, "Verified Support resolves correctly");
assertCondition($authService->canPerform($verifiedSupport, 'invoices_view'), "Verified Support can view invoices");
assertCondition(!$authService->canPerform($verifiedSupport, 'seeder_view'), "Verified Support CANNOT view seeders");

$unverifiedSupport = new User(['email' => 'support@tybuel.io']);
$unverifiedSupport->email_verified_at = null;
assertCondition($authService->resolvePlatformRole($unverifiedSupport) === null, "Unverified Support is strictly denied");

// 4. SpecialistAccount & PortalAccount even with verified emails and matching address
$specialist = new SpecialistAccount(['email' => 'owner@tybuel.io']);
$specialist->email_verified_at = now();
assertCondition($authService->resolvePlatformRole($specialist) === null, "SpecialistAccount with verified owner email is strictly rejected");

$portal = new PortalAccount(['email' => 'owner@tybuel.io']);
$portal->email_verified_at = now();
assertCondition($authService->resolvePlatformRole($portal) === null, "PortalAccount with verified owner email is strictly rejected");

echo "\n=== PART B: Route Isolation from Tenant (Provider & Location) Context ===\n";

$routes = Route::getRoutes();

$adminRoutes = [
    'api/v1/admin/seeders',
    'api/v1/admin/seeders/{seederClass}/run',
    'api/v1/admin/seeders/batch-run',
    'api/v1/admin/platform-payments/pending-invoices',
    'api/v1/admin/platform-payments/invoices/{invoiceId}/verify',
    'api/v1/admin/platform-payments/payment-methods/{id}/details',
    'api/v1/admin/schedule-migration/providers',
    'api/v1/admin/schedule-migration/notify',
];

$providerContextAliases = ['provider.context', 'App\Http\Middleware\ResolveProviderContext'];
$locationContextAliases = ['location.context', 'App\Http\Middleware\ResolveLocationContext'];

foreach ($adminRoutes as $uri) {
    $matchedRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === $uri) {
            $matchedRoute = $route;
            break;
        }
    }

    if (!$matchedRoute) {
        assertCondition(false, "Route '$uri' is registered");
        continue;
    }

    $middleware = $matchedRoute->gatherMiddleware();
    $hasProviderContext = false;
    $hasLocationContext = false;
    $hasSanctum = false;
    $hasPlatformRole = false;

    foreach ($middleware as $m) {
        foreach ($providerContextAliases as $alias) {
            if (str_contains($m, $alias)) $hasProviderContext = true;
        }
        foreach ($locationContextAliases as $alias) {
            if (str_contains($m, $alias)) $hasLocationContext = true;
        }
        if (str_contains($m, 'auth:sanctum') || str_contains($m, 'Authenticate:sanctum')) $hasSanctum = true;
        if (str_contains($m, 'platform.role') || str_contains($m, 'EnsurePlatformRole')) $hasPlatformRole = true;
    }

    assertCondition(!$hasProviderContext, "Route '$uri' is DECOUPLED from provider.context");
    assertCondition(!$hasLocationContext, "Route '$uri' is DECOUPLED from location.context");
    assertCondition($hasSanctum, "Route '$uri' retains auth:sanctum");
    assertCondition($hasPlatformRole, "Route '$uri' retains platform.role authorization");
}

// Check that normal tenant routes RETAIN provider context
$tenantRoutes = [
    'api/v1/pulse',
];

foreach ($tenantRoutes as $uri) {
    $matchedRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === $uri) {
            $matchedRoute = $route;
            break;
        }
    }

    if ($matchedRoute) {
        $middleware = $matchedRoute->gatherMiddleware();
        $hasProviderContext = false;
        $hasLocationContext = false;

        foreach ($middleware as $m) {
            foreach ($providerContextAliases as $alias) {
                if (str_contains($m, $alias)) $hasProviderContext = true;
            }
            foreach ($locationContextAliases as $alias) {
                if (str_contains($m, $alias)) $hasLocationContext = true;
            }
        }

        assertCondition($hasProviderContext, "Tenant route '$uri' RETAINS provider.context");
        assertCondition($hasLocationContext, "Tenant route '$uri' RETAINS location.context");
    }
}

echo "\n============================================\n";
echo "SUMMARY: $passed Passed, $failed Failed\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
