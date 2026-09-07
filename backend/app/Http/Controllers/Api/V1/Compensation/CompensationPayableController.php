<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationPayableController extends CompensationController
{
    /**
     * List all outstanding payables for the salon's team.
     * Outstanding means pending or partial statuses.
     */
    public function index(string $salonSlug, Request $request): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        // Fetch settlements where the salon is the payable entity
        $payables = Settlement::where('payable_type', get_class($salon))
            ->where('payable_id', $salon->id)
            ->whereIn('status', [Settlement::STATUS_PENDING, Settlement::STATUS_PARTIALLY_PAID])
            ->with('recipient')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payables);
    }
}
