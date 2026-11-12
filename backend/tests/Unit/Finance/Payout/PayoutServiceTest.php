<?php

namespace Tests\Unit\Finance\Payout;

use App\Domain\Finance\Payout\Exceptions\AmbiguousDisbursementOutcomeException;
use App\Domain\Finance\Payout\Exceptions\NonRetryableDisbursementException;
use App\Domain\Finance\Payout\Exceptions\RetryableDisbursementException;
use App\Domain\Finance\Payout\Execution\ExecutionStrategy;
use App\Domain\Finance\Payout\PayoutService;
use App\Domain\Finance\Payout\Payout;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\Provider;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayoutService();
    }

    protected function createSettlementFor(float $amount, ?Provider $provider = null, ?Specialist $specialist = null): Settlement
    {
        $provider = $provider ?? Provider::factory()->create();
        $specialist = $specialist ?? Specialist::factory()->create();

        return Settlement::create([
            'provider_id'    => $provider->id,
            'payable_type'   => $provider::class,
            'payable_id'     => $provider->id,
            'recipient_type' => $specialist::class,
            'recipient_id'   => $specialist->id,
            'amount'         => $amount,
            'currency'       => 'UGX',
            'status'         => Settlement::STATUS_PENDING,
        ]);
    }

    public function test_it_initiates_and_executes_manual_payout()
    {
        $settlement = $this->createSettlementFor(50000);

        $payout = $this->service->initiatePayout(
            settlement: $settlement,
            amount: 50000,
            method: 'cash',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'manual',
            payload: ['provider_reference' => 'cash-receipt-1']
        );

        $this->assertEquals(Payout::STATUS_COMPLETED, $payout->status);
        $this->assertEquals('cash', $payout->method);
        $this->assertEquals('manual', $payout->execution_mode);
        $this->assertNotNull($payout->completed_at);
        $this->assertEquals('cash-receipt-1', $payout->provider_reference);
        $this->assertNotNull($payout->idempotency_key);

        $settlement->refresh();
        $this->assertEquals(Settlement::STATUS_PAID, $settlement->status);
    }

    public function test_it_initiates_automated_payout()
    {
        $settlement = $this->createSettlementFor(30000);

        // Resolve an automated strategy via override to skip real provider call,
        // but we still want to exercise the queued→processing transition.
        $payout = $this->service->initiatePayout(
            settlement: $settlement,
            amount: 30000,
            method: 'mtn',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'automated',
            payload: ['payment_profile_id' => null]
        );

        // Status should be one of: processing (strategy reached the transfer call,
        // caught as ambiguous after strategy threw unclassified exception, or
        // even still queued depending on strategy behavior. Either way it is
        // not 'pending' — the dead-code bug is fixed.
        $this->assertNotEquals(Payout::STATUS_PENDING, $payout->status,
            'Dead-code bug: executePayout was never reached; payout stuck pending.');
        $this->assertContains($payout->status, [
            Payout::STATUS_PROCESSING,
            Payout::STATUS_AMBIGUOUS,
        ]);
        $this->assertEquals('automated', $payout->execution_mode);
        $this->assertEquals(0, $payout->attempt_count);

        $settlement->refresh();
        $this->assertEquals(Settlement::STATUS_PENDING, $settlement->status);
    }

    public function test_it_is_idempotent_repeated_execute_on_completed_is_noop()
    {
        $settlement = $this->createSettlementFor(10000);
        $payout = $this->service->initiatePayout(
            settlement: $settlement,
            amount: 10000,
            method: 'cash',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'manual',
            payload: ['provider_reference' => 'r1']
        );
        $this->assertEquals(Payout::STATUS_COMPLETED, $payout->status);
        $completedAt = $payout->completed_at;
        $completedRef = $payout->provider_reference;

        // Second execute call against a completed payout should be a pure no-op.
        $replay = $this->service->executePayout($payout, ['provider_reference' => 'SHOULD_NOT_APPEAR']);

        $this->assertEquals(Payout::STATUS_COMPLETED, $replay->status);
        $this->assertEquals($completedRef, $replay->provider_reference,
            'Replay mutated provider_reference on a completed payout — NOT idempotent.');
        $this->assertEquals($completedAt->toIso8601String(), $replay->completed_at->toIso8601String());
    }

    public function test_it_retries_recoverable_gateway_failure()
    {
        $settlement = $this->createSettlementFor(15000);

        $spy = new class implements ExecutionStrategy {
            public int $calls = 0;
            public function supports(string $method): bool { return true; }
            public function execute(Payout $payout, array $payload = []): void
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new RetryableDisbursementException('insufficient balance at processor');
                }
                // Second call: succeed by simulating markAsCompleted
                $payout->markAsCompleted('ok-' . $this->calls);
            }
        };

        $svc = new class($spy) extends PayoutService {
            public function __construct($strategy) { $this->stub = $strategy; }
            protected function resolveStrategy(Payout $payout): ExecutionStrategy { return $this->stub; }
        };

        // First initiate: call #1 throws retryable → status=failed, attempt_count=0 (only failed→queued increments)
        $payout = $svc->initiatePayout(
            settlement: $settlement,
            amount: 15000,
            method: 'mtn',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'automated'
        );
        $this->assertEquals(Payout::STATUS_FAILED, $payout->status,
            'First call (retryable) must land in status=failed.');
        $this->assertSame(1, $spy->calls);

        // Retry (replay): failed→queued increments attempt_count→1, call #2 succeeds → completed
        $retried = $svc->executePayout($payout);
        $this->assertEquals(Payout::STATUS_COMPLETED, $retried->status,
            'Retry after recoverable failure must reach completed.');
        $this->assertSame(2, $spy->calls);
        $this->assertEquals(1, $retried->attempt_count,
            'attempt_count must be 1 after one retry.');
    }

    public function test_it_marks_nonrecoverable_failure_as_failed_final()
    {
        $settlement = $this->createSettlementFor(20000);

        $strategy = new class implements ExecutionStrategy {
            public function supports(string $method): bool { return true; }
            public function execute(Payout $payout, array $payload = []): void
            {
                throw new NonRetryableDisbursementException('Bad destination account — compliance rejected');
            }
        };
        $svc = new class($strategy) extends PayoutService {
            public function __construct($s) { $this->stub = $s; }
            protected function resolveStrategy(Payout $payout): ExecutionStrategy { return $this->stub; }
        };

        $payout = $svc->initiatePayout(
            settlement: $settlement,
            amount: 20000,
            method: 'mtn',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'automated'
        );
        $this->assertEquals(Payout::STATUS_FAILED_FINAL, $payout->status);
        $this->assertNotNull($payout->last_error_message);

        // Second attempt must throw: you can never retry a failed_final payout.
        $this->expectException(LogicException::class);
        $svc->executePayout($payout);
    }

    public function test_it_marks_ambiguous_outcome_and_blocks_auto_retry()
    {
        $settlement = $this->createSettlementFor(25000);
        $disbKey = 'DISB-' . bin2hex(random_bytes(6));

        $strategy = new class($disbKey) implements ExecutionStrategy {
            public function __construct($k) { $this->k = $k; }
            public function supports(string $method): bool { return true; }
            public function execute(Payout $payout, array $payload = []): void
            {
                // Simulate timeout / connection drop.
                throw new AmbiguousDisbursementOutcomeException(
                    'cURL timeout 28 after 45s',
                    disbursementIdempotencyKey: $this->k
                );
            }
        };
        $svc = new class($strategy) extends PayoutService {
            public function __construct($s) { $this->stub = $s; }
            protected function resolveStrategy(Payout $payout): ExecutionStrategy { return $this->stub; }
        };

        $payout = $svc->initiatePayout(
            settlement: $settlement,
            amount: 25000,
            method: 'mtn',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'automated'
        );

        $this->assertEquals(Payout::STATUS_AMBIGUOUS, $payout->status,
            'Ambiguous outcome must not be retryable via failed status.');
        $this->assertEquals($disbKey, $payout->disbursement_idempotency_key);
        $this->assertNotNull($payout->last_error_at);

        // Block re-execute via service guard (Safeguard 1: NEVER auto-retry ambiguous).
        $caught = false;
        try {
            $svc->executePayout($payout);
        } catch (LogicException $e) {
            if (str_contains($e->getMessage(), 'ambiguous')) $caught = true;
        }
        $this->assertTrue($caught, 'executePayout on ambiguous payout MUST throw LogicException to block retries.');
    }

    public function test_it_protects_against_duplicate_transfer_on_concurrent_execute()
    {
        $settlement = $this->createSettlementFor(30000);

        $spy = new class implements ExecutionStrategy {
            public int $calls = 0;
            public function supports(string $method): bool { return true; }
            public function execute(Payout $payout, array $payload = []): void
            {
                $this->calls++;
                $payout->markAsCompleted('ref-' . $this->calls);
            }
        };
        $svc = new class($spy) extends PayoutService {
            public function __construct($s) { $this->stub = $s; }
            protected function resolveStrategy(Payout $payout): ExecutionStrategy { return $this->stub; }
        };

        $basePayout = $svc->initiatePayout(
            settlement: $settlement,
            amount: 30000,
            method: 'mtn',
            recipientType: $settlement->recipient_type,
            recipientId: $settlement->recipient_id,
            executionMode: 'automated'
        );
        $this->assertEquals(Payout::STATUS_COMPLETED, $basePayout->status);
        $callsAfterFirst = $spy->calls;

        // Simulate concurrent replay: second call with same payout row id (fresh instance
        // as-if separate PHP process).
        $replayRow = Payout::find($basePayout->id);
        $svc->executePayout($replayRow);

        // Strategy execute must have been called EXACTLY $callsAfterFirst times — i.e.
        // the second executePayout call on completed payout short-circuits via guard
        // and strategy.execute is never invoked again.
        $this->assertSame($callsAfterFirst, $spy->calls,
            'Duplicate strategy.execute call on completed payout — duplicate-money BUG.');
    }

    public function test_it_moves_settlement_to_paid_only_when_remaining_is_zero()
    {
        // Full payout case
        $fullSettlement = $this->createSettlementFor(40000);
        $this->service->initiatePayout(
            settlement: $fullSettlement,
            amount: 40000,
            method: 'cash',
            recipientType: $fullSettlement->recipient_type,
            recipientId: $fullSettlement->recipient_id,
            executionMode: 'manual',
        );
        $fullSettlement->refresh();
        $this->assertEquals(Settlement::STATUS_PAID, $fullSettlement->status);

        // Partial payout case
        $partSettlement = $this->createSettlementFor(50000);
        $this->service->initiatePayout(
            settlement: $partSettlement,
            amount: 20000,
            method: 'cash',
            recipientType: $partSettlement->recipient_type,
            recipientId: $partSettlement->recipient_id,
            executionMode: 'manual',
        );
        $partSettlement->refresh();
        $this->assertEquals(Settlement::STATUS_PARTIALLY_PAID, $partSettlement->status,
            'Partial payout should flip settlement to partially_paid, not paid.');
    }
}
