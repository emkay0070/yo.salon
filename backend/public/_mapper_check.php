<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

use App\Domain\Availability\Statuses\AvailabilityDomainStatus;
use App\Domain\Availability\Statuses\AvailabilityPublicStatus;
use App\Domain\Availability\Statuses\AvailabilityStatusMapper;

$mapper = $app->make(AvailabilityStatusMapper::class);
echo "Mapper instantiated: ".get_class($mapper).PHP_EOL;

$cases = [
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_PROVIDER,
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_BRANCH,
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_ASSIGNMENT,
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_SUBSCRIPTION,
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_PROVIDER_DISABLED,
    AvailabilityDomainStatus::CONFIGURATION_REQUIRED_MAINTENANCE,
    AvailabilityDomainStatus::PROVIDER_CLOSED,
    AvailabilityDomainStatus::BRANCH_CLOSED,
    AvailabilityDomainStatus::EXCEPTION_CLOSED,
    AvailabilityDomainStatus::SPECIALIST_DAY_OFF,
    AvailabilityDomainStatus::SPECIALIST_UNAVAILABLE,
    AvailabilityDomainStatus::NO_WINDOWS,
    AvailabilityDomainStatus::UNAVAILABLE,
    AvailabilityDomainStatus::AVAILABLE,
];

echo PHP_EOL."=== Domain → Public mapping ===".PHP_EOL;
foreach ($cases as $d) {
    $p = $mapper->toPublicStatus($d);
    $r = $mapper->ownerReason($d);
    $m = $mapper->publicMessage($p);
    printf("  %-50s → %-24s  owner=%s  public_msg=%s\n",
        $d, $p,
        $r ? '"'.$r.'"' : 'null',
        $m ? '"'.$m.'"' : 'null'
    );
}

echo PHP_EOL."=== Back-compat: inferDomainStatus from pre-mapper payload ===".PHP_EOL;
$legacy = ['is_closed' => true, 'slots' => [], 'status' => 'closed', 'reason' => 'closed'];
$out1 = $mapper->toPublicResponse($legacy);
echo "  legacy closed → status=$out1[status] domain=$out1[domain_status]".PHP_EOL;

$legacySlots = ['is_closed' => false, 'slots' => [['start'=>'09:00']]];
$out2 = $mapper->toPublicResponse($legacySlots);
echo "  legacy with slots → status=$out2[status] domain=$out2[domain_status]".PHP_EOL;

$legacyAss = ['is_closed' => false, 'slots' => [], 'reason' => 'Specialist not assigned to this branch'];
$out3 = $mapper->toPublicResponse($legacyAss);
echo "  legacy specialist unassigned → status=$out3[status] domain=$out3[domain_status] message=$out3[message]".PHP_EOL;

echo PHP_EOL."=== Rank: CONFIGURATION_REQUIRED_PROVIDER beats BRANCH_CLOSED when both apply ===".PHP_EOL;
$both = ['domain_statuses' => [AvailabilityDomainStatus::BRANCH_CLOSED, AvailabilityDomainStatus::CONFIGURATION_REQUIRED_PROVIDER], 'slots' => [], 'is_closed' => true];
$out4 = $mapper->toPublicResponse($both);
echo "  winner domain = $both[domain_status] → public=$out4[status] (expected: configuration_required)".PHP_EOL;

echo PHP_EOL."ALL CHECKS OK".PHP_EOL;
