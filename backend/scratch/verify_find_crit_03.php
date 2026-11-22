<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "Laravel App Booted Successfully for FIND-CRIT-03 Verification!\n";

use App\Models\Customer;
use App\Models\PortalAccount;
use App\Models\Provider;
use App\Models\ProviderUser;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

echo "\n--- 1. Testing Policy Registration in Gate ---\n";
$policy = Gate::getPolicyFor(Customer::class);
assertCondition($policy instanceof CustomerPolicy, "Customer model is bound to CustomerPolicy in Gate");

echo "\n--- 2. Testing CustomerPolicy Direct Methods ---\n";
$policyInstance = new CustomerPolicy();

// Mock request
$mockRequest = Request::create('/api/v1/customers/test-id', 'GET');
$app->instance('request', $mockRequest);

$providerAId = (string) Str::uuid();
$providerBId = (string) Str::uuid();

$customerA = new Customer();
$customerA->id = (string) Str::uuid();

$userA = new User();
$userA->id = (string) Str::uuid();

// Test 2.1: Missing provider context
assertCondition(!$policyInstance->view($userA, $customerA), "Missing provider context returns false");

// Test 2.2: Non-User principal (PortalAccount)
$portalAccount = new PortalAccount();
$portalAccount->id = (string) Str::uuid();
$mockRequest->attributes->set('provider_id', $providerAId);
assertCondition(!$policyInstance->view($portalAccount, $customerA), "PortalAccount principal is denied");

// Test 2.3: Non-User principal (SpecialistAccount)
$specialistAccount = new SpecialistAccount();
$specialistAccount->id = (string) Str::uuid();
assertCondition(!$policyInstance->view($specialistAccount, $customerA), "SpecialistAccount principal is denied");

echo "\n--- Verification Finished. Passed: $passed, Failed: $failed ---\n";
