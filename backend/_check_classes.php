<?php

require __DIR__ . '/vendor/autoload.php';

$classes = [
    \Tests\Unit\Finance\Payout\PayoutServiceTest::class,
    \Tests\Unit\Listeners\CreateRefundNotificationTest::class,
    \App\Domain\Finance\Payout\Exceptions\RetryableDisbursementException::class,
    \App\Domain\Finance\Payout\Exceptions\NonRetryableDisbursementException::class,
    \App\Domain\Finance\Payout\Exceptions\AmbiguousDisbursementOutcomeException::class,
    \App\Listeners\CreateRefundNotification::class,
];

foreach ($classes as $c) {
    try {
        $r = new ReflectionClass($c);
        echo "OK  {$c}" . PHP_EOL;
        if (str_contains($c, 'Test')) {
            $methods = array_map(fn($m) => $m->getName(), array_filter($r->getMethods(), fn($m) => str_starts_with($m->getName(), 'test')));
            echo "     -> test methods: " . implode(', ', $methods) . PHP_EOL;
        }
    } catch (\Throwable $e) {
        echo "FAIL {$c}: " . $e->getMessage() . PHP_EOL;
    }
}
