<?php
// Standalone test - no Laravel bootstrap needed
// Just test the wildcard pattern that DiscoverEvents uses

function strIs(string $pattern, string $value): bool {
    if ($pattern === $value) return true;
    $pattern = preg_quote($pattern, '#');
    $pattern = str_replace('\*', '.*', $pattern);
    return (bool) preg_match('#^' . $pattern . '\z#u', $value);
}

$methods = ['handle', 'handlePaymentCaptured', 'handleRefundCaptured', '__invoke', 'boot', 'register'];
foreach ($methods as $m) {
    $matchesHandle = strIs('handle*', $m);
    $matchesInvoke = strIs('__invoke', $m);
    $discovered = $matchesHandle || $matchesInvoke;
    echo sprintf("%-30s handle*=%-3s  __invoke=%-3s  => DISCOVERED=%s\n",
        $m,
        $matchesHandle ? 'YES' : 'no',
        $matchesInvoke ? 'YES' : 'no',
        $discovered ? 'YES' : 'no'
    );
}
