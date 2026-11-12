<?php

namespace App\Domain\Finance\Payout\Exceptions;

use RuntimeException;

/**
 * Provider outcome is UNKNOWN (time-out, ambiguous 5xx, connection drop mid-call,
 * provider returned no response body / no confirmation).
 *
 * Payout MUST be marked `ambiguous` and automatic retry MUST be blocked until
 * reconciliation resolves the real outcome via `DisbursementProvider::queryStatus`.
 * Otherwise duplicate real disbursements can occur.
 */
class AmbiguousDisbursementOutcomeException extends RuntimeException
{
    public ?string $disbursementIdempotencyKey;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $disbursementIdempotencyKey = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->disbursementIdempotencyKey = $disbursementIdempotencyKey;
    }
}
