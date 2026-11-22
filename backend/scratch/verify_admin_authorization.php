<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "Laravel App Booted Successfully!\n";

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use App\Models\SpecialistAccount;
use App\Models\PortalAccount;
use App\Services\PlatformAuthorizationService;
use Illuminate\Http\Request;

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

echo "\n--- 1. Testing PlatformAuthorizationService Role Resolution ---\n";
$ordinaryUser = new User(['email' => 'regular@user.com']);
assertCondition($authService->resolvePlatformRole($ordinaryUser) === null, "Ordinary user has no platform role");
assertCondition(!$authService->hasPlatformRole($ordinaryUser, 'platform_owner'), "Ordinary user is not platform owner");
assertCondition(!$authService->canPerform($ordinaryUser, 'seeder_view'), "Ordinary user cannot view seeders");
assertCondition(!$authService->canPerform($ordinaryUser, 'seeder_execution'), "Ordinary user cannot execute seeders");
assertCondition(!$authService->canPerform($ordinaryUser, 'invoices_view'), "Ordinary user cannot view invoices");
assertCondition(!$authService->canPerform($ordinaryUser, 'invoices_verify_reject'), "Ordinary user cannot verify/reject invoices");

$ownerUser = new User(['email' => 'owner@tybuel.io']);
$ownerUser->platform_role = PlatformRole::PLATFORM_OWNER;
assertCondition($authService->resolvePlatformRole($ownerUser) === PlatformRole::PLATFORM_OWNER, "Owner user resolves to platform_owner");
assertCondition($authService->canPerform($ownerUser, 'seeder_view'), "Platform owner can view seeders");
assertCondition($authService->canPerform($ownerUser, 'seeder_execution'), "Platform owner can execute seeders");
assertCondition($authService->canPerform($ownerUser, 'invoices_view'), "Platform owner can view invoices");
assertCondition($authService->canPerform($ownerUser, 'invoices_verify_reject'), "Platform owner can verify/reject invoices");
assertCondition($authService->canPerform($ownerUser, 'payment_methods_manage'), "Platform owner can manage payment methods");
assertCondition($authService->canPerform($ownerUser, 'payment_methods_view_details'), "Platform owner can view payment method details");

$superAdminUser = new User(['email' => 'superadmin@tybuel.io']);
$superAdminUser->platform_role = PlatformRole::PLATFORM_SUPER_ADMIN;
assertCondition($authService->resolvePlatformRole($superAdminUser) === PlatformRole::PLATFORM_SUPER_ADMIN, "Super admin user resolves to platform_super_admin");
assertCondition($authService->canPerform($superAdminUser, 'seeder_execution'), "Super admin can execute seeders");
assertCondition($authService->canPerform($superAdminUser, 'invoices_verify_reject'), "Super admin can verify/reject invoices");

$devUser = new User(['email' => 'developer@tybuel.io']);
$devUser->platform_role = PlatformRole::DEVELOPER;
assertCondition($authService->resolvePlatformRole($devUser) === PlatformRole::DEVELOPER, "Developer user resolves to developer");
assertCondition($authService->canPerform($devUser, 'seeder_view'), "Developer can view seeders");
assertCondition(!$authService->canPerform($devUser, 'seeder_execution'), "Developer CANNOT execute seeders");
assertCondition($authService->canPerform($devUser, 'invoices_view'), "Developer can view invoices");
assertCondition(!$authService->canPerform($devUser, 'invoices_verify_reject'), "Developer CANNOT verify/reject invoices");
assertCondition(!$authService->canPerform($devUser, 'payment_methods_view_details'), "Developer CANNOT view payment method details");

$supportUser = new User(['email' => 'support@tybuel.io']);
$supportUser->platform_role = PlatformRole::SUPPORT;
assertCondition($authService->resolvePlatformRole($supportUser) === PlatformRole::SUPPORT, "Support user resolves to support");
assertCondition(!$authService->canPerform($supportUser, 'seeder_view'), "Support CANNOT view seeders");
assertCondition(!$authService->canPerform($supportUser, 'seeder_execution'), "Support CANNOT execute seeders");
assertCondition($authService->canPerform($supportUser, 'invoices_view'), "Support can view invoices");
assertCondition(!$authService->canPerform($supportUser, 'invoices_verify_reject'), "Support CANNOT verify/reject invoices");
assertCondition(!$authService->canPerform($supportUser, 'payment_methods_manage'), "Support CANNOT manage payment methods");
assertCondition(!$authService->canPerform($supportUser, 'payment_methods_view_details'), "Support CANNOT view payment method details");

echo "\n--- 2. Testing Non-User Principals (SpecialistAccount, PortalAccount) ---\n";
$specialistAccount = new SpecialistAccount(['email' => 'stylist@tybuel.io']);
$specialistAccount->platform_role = 'platform_owner'; // Even if someone sets it dynamically
assertCondition($authService->resolvePlatformRole($specialistAccount) === null, "SpecialistAccount is strictly rejected from role resolution");
assertCondition(!$authService->hasPlatformRole($specialistAccount, 'platform_owner'), "SpecialistAccount has no platform role");
assertCondition(!$authService->canPerform($specialistAccount, 'invoices_view'), "SpecialistAccount cannot view invoices");

$portalAccount = new PortalAccount(['email' => 'customer@tybuel.io']);
$portalAccount->platform_role = 'platform_owner';
assertCondition($authService->resolvePlatformRole($portalAccount) === null, "PortalAccount is strictly rejected from role resolution");
assertCondition(!$authService->hasPlatformRole($portalAccount, 'platform_owner'), "PortalAccount has no platform role");

echo "\n--- 3. Testing EnsurePlatformRole Middleware Directly ---\n";
$middleware = new \App\Http\Middleware\EnsurePlatformRole($authService);

function runMiddleware($app, $middleware, $request, ...$args) {
    $app->instance('request', $request);
    return $middleware->handle($request, function ($r) { return response()->json(['status' => 'OK']); }, ...$args);
}

// Test unauthenticated request
$reqGuest = Request::create('/api/v1/admin/seeders', 'GET');
$resGuest = runMiddleware($app, $middleware, $reqGuest, 'seeder_view');
assertCondition($resGuest->getStatusCode() === 401, "Unauthenticated request returns 401");

// Test SpecialistAccount request
$reqSpecialist = Request::create('/api/v1/admin/seeders', 'GET');
$reqSpecialist->setUserResolver(fn () => $specialistAccount);
echo "Direct reqSpecialist user: "; var_dump($reqSpecialist->user());
$resSpecialist = runMiddleware($app, $middleware, $reqSpecialist, 'seeder_view');
echo "DEBUG Specialist code: " . $resSpecialist->getStatusCode() . ", content: " . $resSpecialist->getContent() . "\n";
assertCondition($resSpecialist->getStatusCode() === 403, "SpecialistAccount request returns 403");

// Test PortalAccount request
$reqPortal = Request::create('/api/v1/admin/seeders', 'GET');
$reqPortal->setUserResolver(fn () => $portalAccount);
$resPortal = runMiddleware($app, $middleware, $reqPortal, 'seeder_view');
assertCondition($resPortal->getStatusCode() === 403, "PortalAccount request returns 403");

// Test Ordinary User request on seeder_view
$reqOrdinary = Request::create('/api/v1/admin/seeders', 'GET');
$reqOrdinary->setUserResolver(fn () => $ordinaryUser);
$resOrdinary = runMiddleware($app, $middleware, $reqOrdinary, 'seeder_view');
assertCondition($resOrdinary->getStatusCode() === 403, "Ordinary User returns 403 on seeder_view");

// Test Ordinary User request on invoices_verify_reject
$reqOrdinaryVerify = Request::create('/api/v1/admin/platform-payments/invoices/123/verify', 'POST');
$reqOrdinaryVerify->setUserResolver(fn () => $ordinaryUser);
$resOrdinaryVerify = runMiddleware($app, $middleware, $reqOrdinaryVerify, 'invoices_verify_reject');
assertCondition($resOrdinaryVerify->getStatusCode() === 403, "Ordinary User returns 403 on invoices_verify_reject");

// Test Developer request on seeder_view (allowed) and seeder_execution (blocked)
$reqDev = Request::create('/api/v1/admin/seeders', 'GET');
$reqDev->setUserResolver(fn () => $devUser);
$resDevView = runMiddleware($app, $middleware, $reqDev, 'seeder_view');
assertCondition($resDevView->getStatusCode() === 200, "Developer is permitted 200 on seeder_view");

$reqDevRun = Request::create('/api/v1/admin/seeders/Test/run', 'POST');
$reqDevRun->setUserResolver(fn () => $devUser);
$resDevRun = runMiddleware($app, $middleware, $reqDevRun, 'seeder_execution');
assertCondition($resDevRun->getStatusCode() === 403, "Developer is blocked 403 on seeder_execution");

// Test Support request on invoices_view (allowed) and seeder_view (blocked)
$reqSupport = Request::create('/api/v1/admin/platform-payments/pending-invoices', 'GET');
$reqSupport->setUserResolver(fn () => $supportUser);
$resSupportView = runMiddleware($app, $middleware, $reqSupport, 'invoices_view');
assertCondition($resSupportView->getStatusCode() === 200, "Support is permitted 200 on invoices_view");

$reqSupportSeeder = Request::create('/api/v1/admin/seeders', 'GET');
$reqSupportSeeder->setUserResolver(fn () => $supportUser);
$resSupportSeeder = runMiddleware($app, $middleware, $reqSupportSeeder, 'seeder_view');
assertCondition($resSupportSeeder->getStatusCode() === 403, "Support is blocked 403 on seeder_view");

// Test Platform Owner request on seeder_execution (allowed in non-production)
$reqOwnerRun = Request::create('/api/v1/admin/seeders/Test/run', 'POST');
$reqOwnerRun->setUserResolver(fn () => $ownerUser);
$resOwnerRun = runMiddleware($app, $middleware, $reqOwnerRun, 'seeder_execution');
assertCondition($resOwnerRun->getStatusCode() === 200, "Platform Owner is permitted 200 on seeder_execution in non-production");

echo "\n--- 4. Testing Production Environment Safeguard ---\n";
// Simulate production environment
$app->detectEnvironment(fn () => 'production');
$resOwnerRunProd = runMiddleware($app, $middleware, $reqOwnerRun, 'seeder_execution');
assertCondition($resOwnerRunProd->getStatusCode() === 403, "Platform Owner is strictly BLOCKED 403 on seeder_execution in production");
$content = json_decode($resOwnerRunProd->getContent(), true);
assertCondition(isset($content['message']) && str_contains($content['message'], 'disabled in production'), "Response explains seeding is disabled in production");

echo "\n============================================\n";
echo "VERIFICATION SUMMARY: $passed Passed, $failed Failed\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
