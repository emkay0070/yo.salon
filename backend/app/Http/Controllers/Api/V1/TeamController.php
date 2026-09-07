<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Staff;
use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * TeamController returns a unified list of all entities the salon can owe money to.
 *
 * Every payee is normalized into the same TeamMember shape regardless of whether
 * they are a Staff member (employee, receptionist), an employed specialist, or
 * an independent specialist assigned to the salon.
 *
 * The compensation and payable sub-objects mean the frontend does NOT need to
 * call multiple endpoints to render the Team Payments screen — it receives
 * everything it needs in a single response.
 */
class TeamController extends Controller
{
    public function index(string $salonSlug, Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();

        if (!$salon || $salon->slug !== $salonSlug) {
            return response()->json(['message' => 'Unauthorized access to salon team.'], 403);
        }

        $members = collect();

        // ── 1. Staff (employees, receptionists, managers) ──────────────────────
        $staff = Staff::where('salon_id', $salon->id)
            ->where('active', true)
            ->get();

        foreach ($staff as $member) {
            $policy = $this->activePolicy(\App\Models\Staff::class, $member->id, \App\Models\Salon::class, $salon->id);
            $payable = $this->outstandingPayable(\App\Models\Staff::class, $member->id, \App\Models\Salon::class, $salon->id);

            $members->push([
                'id'        => $member->id,
                'type'      => 'staff',
                'name'      => $member->name,
                'email'     => $member->email,
                'phone'     => $member->phone,
                'role'      => $member->role ?? 'staff',
                'photo_url' => $member->photo_url,

                'compensation' => $this->formatPolicy($policy),
                'payable'      => $payable,
            ]);
        }

        // ── 2. Specialists assigned to this salon ───────────────────────────────
        // Covers both:
        //   (a) Independent specialists  — assignment.staff_id IS NULL
        //   (b) Employed specialists     — assignment.staff_id IS NOT NULL
        // Both belong on the Team Payments screen as they both earn from the salon.
        $assignments = SpecialistAssignment::where('salon_id', $salon->id)
            ->where('status', 'active')
            ->with('specialist')
            ->get();

        foreach ($assignments as $assignment) {
            $specialist = $assignment->specialist;
            if (!$specialist) {
                continue;
            }

            // Determine sub-type: employed vs independent
            $subtype = isset($assignment->staff_id) ? 'employed_specialist' : 'independent_specialist';

            $policy = $this->activePolicy(\App\Models\Specialist::class, $specialist->id, \App\Models\Salon::class, $salon->id, $assignment->id);
            $payable = $this->outstandingPayable(\App\Models\Specialist::class, $specialist->id, \App\Models\Salon::class, $salon->id);

            // Avoid duplicate: if employed specialist also has a Staff record in
            // this salon, we've already included them above. Skip.
            if ($subtype === 'employed_specialist' && $members->contains('id', $assignment->staff_id)) {
                continue;
            }

            $members->push([
                'id'         => $specialist->id,
                'type'       => $subtype,
                'name'       => $specialist->name,
                'email'      => $specialist->email,
                'phone'      => $specialist->phone,
                'role'       => $specialist->role ?? 'specialist',
                'photo_url'  => $specialist->photo_url,
                'assignment_id' => $assignment->id,

                'compensation' => $this->formatPolicy($policy),
                'payable'      => $payable,
            ]);
        }

        return response()->json([
            'salon_id' => $salon->id,
            'team'     => $members->values(),
            'total'    => $members->count(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Get the currently active CompensationPolicy for a given payee + salon.
     */
    private function activePolicy(
        string $compensatableType,
        string $compensatableId,
        string $providerType,
        string $providerId,
        ?string $assignmentId = null
    ): ?CompensationPolicy {
        $query = CompensationPolicy::active()
            ->forCompensatable($compensatableType, $compensatableId)
            ->forProvider($providerType, $providerId);

        if ($assignmentId) {
            $query->where(function ($q) use ($assignmentId) {
                $q->forAssignment($assignmentId)->orWhereNull('assignment_id');
            });
        }

        return $query->first();
    }

    /**
     * Get outstanding payment obligations for a given payee from this salon.
     * This reads from Settlement — not from bookings.
     */
    private function outstandingPayable(
        string $recipientType,
        string $recipientId,
        string $payableType,
        string $payableId
    ): array {
        $settlements = Settlement::where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('payable_type', $payableType)
            ->where('payable_id', $payableId)
            ->whereIn('status', [Settlement::STATUS_PENDING, Settlement::STATUS_PARTIALLY_PAID])
            ->get();

        return [
            'amount'           => (float) $settlements->sum('amount'),
            'currency'         => $settlements->first()?->currency ?? 'UGX',
            'settlement_count' => $settlements->count(),
            'settlement_ids'   => $settlements->pluck('id'),
        ];
    }

    /**
     * Shape a CompensationPolicy into a frontend-safe summary.
     */
    private function formatPolicy(?CompensationPolicy $policy): array
    {
        if (!$policy) {
            return [
                'policy_id'            => null,
                'type'                 => null,
                'summary'              => 'No compensation policy configured',
                'settlement_frequency' => null,
            ];
        }

        return [
            'policy_id'            => $policy->id,
            'type'                 => $policy->type,
            'summary'              => $this->summarizePolicy($policy),
            'settlement_frequency' => $policy->settlement_frequency,
        ];
    }

    /**
     * Human-readable one-liner for the policy, e.g. "40% commission, monthly"
     */
    private function summarizePolicy(CompensationPolicy $policy): string
    {
        $firstRule = $policy->rules[0] ?? null;

        return match ($policy->type) {
            'commission' => ($firstRule['rate'] ?? 0) . '% commission, ' . $policy->settlement_frequency,
            'salary'     => 'UGX ' . number_format($firstRule['amount'] ?? 0) . ' salary, ' . $policy->settlement_frequency,
            'per_booking'=> 'UGX ' . number_format($firstRule['amount'] ?? 0) . ' per booking, ' . $policy->settlement_frequency,
            'hybrid'     => 'Hybrid (base + commission), ' . $policy->settlement_frequency,
            default      => $policy->type . ', ' . $policy->settlement_frequency,
        };
    }
}
