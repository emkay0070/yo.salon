<?php

namespace App\Domain\Finance\Payout\Exceptions;

use RuntimeException;

/**
 * Provider definitively said NO (e.g. rejected by processor, insufficient
 * settlement balance on their side). It is SAFE to retry because the provider
 * explicitly confirmed the money was NOT applied.
 */
class RetryableDisbursementException extends RuntimeException
{
}
