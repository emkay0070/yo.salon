<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\CompensationAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationAdjustmentController extends CompensationController
{
    /**
     * Add an adjustment (bonus, deduction, tip) to an open period.
     */
    public function store(string $salonSlug, string $periodId, Request $request): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        $period = CompensationPeriod::where('provider_type', get_class($salon))
            ->where('provider_id', $salon->id)
            ->where('id', $periodId)
            ->firstOrFail();

        if ($period->status !== CompensationPeriod::STATUS_OPEN) {
            return response()->json(['message' => 'Cannot add adjustments to a closed period.'], 422);
        }

        $validated = $request->validate([
            'type'        => 'required|string|in:bonus,deduction,tip,allowance',
            'amount'      => 'required|numeric',
            'description' => 'required|string|max:255',
        ]);

        $adjustment = CompensationAdjustment::create([
            'compensation_period_id' => $period->id,
            'type'                   => $validated['type'],
            'amount'                 => $validated['amount'],
            'currency'               => $period->currency,
            'description'            => $validated['description'],
            'created_by'             => auth()->id(),
        ]);

        return response()->json($adjustment, 201);
    }
}
