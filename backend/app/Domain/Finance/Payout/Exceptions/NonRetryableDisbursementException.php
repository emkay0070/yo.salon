<?php

namespace App\Domain\Finance\Payout\Exceptions;

use RuntimeException;

/**
 * Provider definitively rejected the transfer (e.g. bad account, compliance
 * reject). Payout MUST be marked failed_final; never retry.
 */
class NonRetryableDisbursementException extends RuntimeException
{
}
