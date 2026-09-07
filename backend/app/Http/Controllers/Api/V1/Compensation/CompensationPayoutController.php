<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Payout\PayoutService;
use App\Domain\Finance\Settlement\Settlement;
use App\Services\Payments\PayoutReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationPayoutController extends CompensationController
{
    protected PayoutService $payoutService;
    protected PayoutReadinessService $readinessService;

    public function __construct(
        PayoutService $payoutService,
        PayoutReadinessService $readinessService,
    ) {
        $this->payoutService    = $payoutService;
        $this->readinessService = $readinessService;
    }

    /**
     * Return payout readiness for a settlement.
     *
     * The frontend NEVER decides whether automated payout is safe.
     * It calls this endpoint and renders only what is returned.
     */
    public function readiness(string $salonSlug, string $settlementId): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        $settlement = Settlement::where('payable_type', get_class($salon))
            ->where('payable_id', $salon->id)
            ->where('id', $settlementId)
            ->firstOrFail();

        $dto = $this->readinessService->check($settlement);

        return response()->json($dto->toArray());
    }

    /**
     * Execute or record a payout against an obligation.
     */
    public function store(string $salonSlug, string $settlementId, Request $request): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        $settlement = Settlement::where('payable_type', get_class($salon))
            ->where('payable_id', $salon->id)
            ->where('id', $settlementId)
            ->firstOrFail();

        $validated = $request->validate([
            'amount'             => 'required|numeric|min:0.01',
            'method'             => 'required|string', // mtn, airtel, bank, cash
            'execution_mode'     => 'required|string|in:manual,automated',
            'provider'           => 'nullable|string',
            'provider_reference' => 'nullable|string',
            'payment_profile_id' => 'nullable|uuid',
        ]);

        // Guard: automated is only allowed if readiness confirms it
        if ($validated['execution_mode'] === 'automated') {
            $readiness = $this->readinessService->check($settlement);
            if (!$readiness->automatedAvailable) {
                return response()->json([
                    'message' => 'Automated payout is not available: ' . $readiness->reason,
                    'readiness' => $readiness->toArray(),
                ], 422);
            }
        }

        try {
            $payout = $this->payoutService->initiatePayout(
                settlement:    $settlement,
                amount:        (float) $validated['amount'],
                method:        $validated['method'],
                executionMode: $validated['execution_mode'],
                payload:       [
                    'provider'           => $validated['provider'] ?? null,
                    'provider_reference' => $validated['provider_reference'] ?? null,
                    'payment_profile_id' => $validated['payment_profile_id'] ?? null,
                    'processor_id'       => auth()->id(),
                ]
            );

            return response()->json($payout, 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

