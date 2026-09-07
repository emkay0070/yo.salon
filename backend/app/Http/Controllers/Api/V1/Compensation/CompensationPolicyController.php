<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Models\Specialist;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CompensationPolicyController extends CompensationController
{
    /**
     * List policies for the salon's team.
     */
    public function index(string $salonSlug, Request $request): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        $policies = CompensationPolicy::where('provider_type', get_class($salon))
            ->where('provider_id', $salon->id)
            ->with('compensatable')
            ->orderBy('effective_from', 'desc')
            ->get();

        return response()->json($policies);
    }

    /**
     * Create a new policy. Correctly sets effective_until on the previous active policy.
     */
    public function store(string $salonSlug, Request $request): JsonResponse
    {
        $salon = $this->authorizeSalonManager($salonSlug);

        $validated = $request->validate([
            'compensatable_id'     => 'required|uuid',
            'compensatable_type'   => 'required|string', // e.g. App\Models\Specialist
            'type'                 => 'required|string|in:commission,salary,hybrid,per_booking,fixed',
            'rules'                => 'required|array',
            'settlement_frequency' => 'required|string|in:immediate,weekly,biweekly,monthly',
            'effective_from'       => 'required|date',
        ]);

        $effectiveFrom = Carbon::parse($validated['effective_from'])->startOfDay();

        $policy = DB::transaction(function () use ($salon, $validated, $effectiveFrom) {
            
            // 1. Find existing active policy that might overlap
            $existingActive = CompensationPolicy::where('provider_type', get_class($salon))
                ->where('provider_id', $salon->id)
                ->where('compensatable_type', $validated['compensatable_type'])
                ->where('compensatable_id', $validated['compensatable_id'])
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('effective_until')
                      ->orWhere('effective_until', '>=', now()->toDateString());
                })
                ->first();

            // 2. Adjust the existing policy's end date
            if ($existingActive) {
                $existingFrom = Carbon::parse($existingActive->effective_from)->startOfDay();
                
                if ($effectiveFrom->lte($existingFrom)) {
                    // New policy starts before or on the same day as the existing one.
                    // We must void/deactivate the existing one completely.
                    $existingActive->update([
                        'is_active' => false,
                        'effective_until' => $effectiveFrom->copy()->subDay()->toDateString(),
                    ]);
                } else {
                    // Normal case: new policy supersedes the old one starting on $effectiveFrom
                    $existingActive->update([
                        'effective_until' => $effectiveFrom->copy()->subDay()->toDateString(),
                    ]);
                }
            }

            // 3. Create the new policy
            return CompensationPolicy::create([
                'provider_type'        => get_class($salon),
                'provider_id'          => $salon->id,
                'compensatable_type'   => $validated['compensatable_type'],
                'compensatable_id'     => $validated['compensatable_id'],
                'type'                 => $validated['type'],
                'rules'                => $validated['rules'],
                'settlement_frequency' => $validated['settlement_frequency'],
                'effective_from'       => $effectiveFrom->toDateString(),
                'is_active'            => true,
                'created_by'           => auth()->id(),
            ]);
        });

        return response()->json($policy, 201);
    }
}
