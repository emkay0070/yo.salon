<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationPayableController extends CompensationController
{
    /**
     * List all outstanding payables for the provider's team.
     * Outstanding means pending or partial statuses.
     */
    public function index(string $providerIdentifier, Request $request): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        // Fetch settlements where the provider is the payable entity
        $payables = Settlement::where('payable_type', get_class($provider))
            ->where('payable_id', $provider->id)
            ->whereIn('status', [Settlement::STATUS_PENDING, Settlement::STATUS_PARTIALLY_PAID])
            ->with('recipient')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payables);
    }
}
