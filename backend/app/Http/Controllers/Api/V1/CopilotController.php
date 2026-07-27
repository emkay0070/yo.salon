<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Services\IntelligenceEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    protected IntelligenceEngine $engine;

    public function __construct(IntelligenceEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Handle a chat message from the salon owner.
     * Uses a deterministic NLP approach to match intents and respond.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'context' => 'nullable|array'
        ]);

        $message = strtolower(trim($request->input('message')));
        $context = $request->input('context');
        $salonId = auth()->user()->currentSalon()?->id;

        if ($context) {
            $response = $this->matchIntentWithContext($message, $context);
        } else {
            // Legacy computation
            $transactions = Transaction::where('salon_id', $salonId)
                ->whereIn('status', ['completed', 'paid'])
                ->get();
            $bookings = Booking::with(['customer', 'staff', 'service'])
                ->where('salon_id', $salonId)
                ->get();
            $response = $this->matchIntent($message, $transactions, $bookings);
        }

        return response()->json([
            'message' => $response['text'],
            'data'    => $response['data'] ?? null,
            'type'    => $response['type'] ?? 'text',
        ]);
    }

    private function matchIntentWithContext(string $message, array $context): array
    {
        // ── Intent: Churn / At Risk ──────────────────────────────────────────
        if ($this->matches($message, ['churn', 'at risk', 'missing', 'lost', 'havent come back', "haven't come back"])) {
            $atRiskCount = count($context['churn_customer_ids'] ?? []);
            if ($atRiskCount > 0) {
                return [
                    'type' => 'warning',
                    'text' => "⚠️ **Churn Risk Detected:** You have **{$atRiskCount} regular customers** at risk of churning right now. I recommend sending them a personalised 'We miss you — here's 10% off' campaign via SMS.",
                    'data' => [['label' => 'At-Risk Customers', 'value' => $atRiskCount]]
                ];
            }
            return ['type' => 'success', 'text' => "✅ No significant churn risk detected right now. Your regular customers are coming back consistently!"];
        }

        // ── Intent: Staff ────────────────────────────────────────────────────
        if ($this->matches($message, ['staff', 'employee', 'who is', 'best stylist', 'team', 'top'])) {
            if (!empty($context['top_staff_id'])) {
                // In a real scenario we'd query the specific staff by ID, but context might just have ID.
                // Assuming we might not have the name without a query, we just give a generic answer or query it.
                $staff = \App\Models\Staff::find($context['top_staff_id']);
                if ($staff) {
                    return [
                        'type' => 'success',
                        'text' => "Your **top performing team member** right now is **{$staff->name}**.",
                    ];
                }
            }
            return ['type' => 'text', 'text' => "I don't have a clear top performer right now."];
        }

        // ── Intent: Slow Days ────────────────────────────────────────────────
        if ($this->matches($message, ['slow', 'quiet', 'dead', 'day', 'week'])) {
            $slowest = $context['slowest_day'] ?? 'Unknown';
            return [
                'type' => 'text',
                'text' => "Based on recent intelligence, **{$slowest}** is your slowest day. Consider a **\"{$slowest} Special\"** promotion to drive volume on that day.",
            ];
        }

        // Fallback for context-based chat
        return [
            'type' => 'text',
            'text' => "I am answering using **Intelligence Context**! Try asking me about churn risks, staff performance, or your slowest day."
        ];
    }

    /**
     * Deterministic NLP intent matcher.
     */
    private function matchIntent(string $message, $transactions, $bookings): array
    {
        $totalGross = $transactions->sum('gross_amount');
        $totalNet   = $transactions->sum('net_amount');
        $totalFees  = $transactions->sum(fn($tx) => $tx->gateway_fee + $tx->platform_fee);
        $totalBookings = $bookings->count();
        $uniqueCustomers = $bookings->pluck('customer_id')->unique()->count();

        // ── Intent: Revenue ──────────────────────────────────────────────────
        if ($this->matches($message, ['revenue', 'how much', 'money', 'earned', 'profit', 'net', 'gross'])) {
            $feePercent = $totalGross > 0 ? round(($totalFees / $totalGross) * 100, 1) : 0;
            return [
                'type' => 'metric',
                'text' => "Your business has earned **UGX " . number_format($totalGross) . "** in gross revenue. After deducting **UGX " . number_format($totalFees) . "** in gateway fees ({$feePercent}%), your **net profit is UGX " . number_format($totalNet) . "**.",
                'data' => [
                    ['label' => 'Gross Revenue', 'value' => 'UGX ' . number_format($totalGross)],
                    ['label' => 'Total Fees',    'value' => 'UGX ' . number_format($totalFees)],
                    ['label' => 'Net Profit',    'value' => 'UGX ' . number_format($totalNet)],
                ]
            ];
        }

        // ── Intent: Bookings ─────────────────────────────────────────────────
        if ($this->matches($message, ['bookings', 'appointments', 'how many', 'clients', 'customers', 'busy'])) {
            $todayBookings = $bookings->filter(fn($b) => $b->date === now()->toDateString())->count();
            return [
                'type' => 'metric',
                'text' => "You have had **{$totalBookings} bookings** in total from **{$uniqueCustomers} unique customers**. Today, you have **{$todayBookings} appointments** on the schedule.",
                'data' => [
                    ['label' => 'Total Bookings', 'value' => $totalBookings],
                    ['label' => 'Unique Customers', 'value' => $uniqueCustomers],
                    ['label' => 'Today',           'value' => $todayBookings],
                ]
            ];
        }

        // ── Intent: Churn / At Risk ──────────────────────────────────────────
        if ($this->matches($message, ['churn', 'at risk', 'missing', 'lost', 'havent come back', "haven't come back"])) {
            $customerGroups = $bookings->groupBy('customer_id');
            $atRisk = 0;
            foreach ($customerGroups as $customerId => $group) {
                if ($customerId && $group->count() > 1) {
                    $last = collect($group)->max('date');
                    $days = now()->diffInDays(\Carbon\Carbon::parse($last));
                    if ($days >= 45 && $days <= 90) $atRisk++;
                }
            }
            if ($atRisk > 0) {
                return [
                    'type' => 'warning',
                    'text' => "⚠️ **Churn Risk Detected:** **{$atRisk} regular customers** haven't visited in over 45 days. I recommend sending them a personalised 'We miss you — here's 10% off' campaign via SMS.",
                    'data' => [['label' => 'At-Risk Customers', 'value' => $atRisk]]
                ];
            } else {
                return ['type' => 'success', 'text' => "✅ No significant churn risk detected. Your regular customers are coming back consistently!"];
            }
        }

        // ── Intent: Top Service ──────────────────────────────────────────────
        if ($this->matches($message, ['service', 'popular', 'best', 'top', 'performing'])) {
            $topServiceGroup = $bookings->groupBy('service_id')->sortByDesc(fn($g) => $g->count())->first();
            if ($topServiceGroup) {
                $topService = $topServiceGroup->first()->service;
                $count = $topServiceGroup->count();
                return [
                    'type' => 'metric',
                    'text' => "Your **top performing service** is **{$topService?->name}** with **{$count} bookings**.",
                    'data' => [
                        ['label' => 'Service',  'value' => $topService?->name],
                        ['label' => 'Bookings', 'value' => $count],
                    ]
                ];
            }
        }

        // ── Intent: Staff ────────────────────────────────────────────────────
        if ($this->matches($message, ['staff', 'employee', 'who is', 'best stylist', 'team'])) {
            $staffGroups = $bookings->whereNotNull('staff_id')->groupBy('staff_id')->sortByDesc(fn($g) => $g->count());
            if ($staffGroups->count() > 0) {
                $topGroup = $staffGroups->first();
                $topStaff = $topGroup->first()->staff;
                $count = $topGroup->count();
                return [
                    'type' => 'success',
                    'text' => "Your **top performing team member** is **{$topStaff?->name}** who has handled **{$count} bookings**.",
                    'data' => [
                        ['label' => 'Staff Member', 'value' => $topStaff?->name],
                        ['label' => 'Bookings',     'value' => $count],
                    ]
                ];
            }
        }

        // ── Intent: Health / Overview ────────────────────────────────────────
        if ($this->matches($message, ['how', 'doing', 'health', 'overview', 'summary', 'status', 'performance'])) {
            $summary = $this->engine->generateExecutiveSummary($transactions, $bookings);
            return ['type' => 'text', 'text' => $summary];
        }

        // ── Intent: Slow Days ────────────────────────────────────────────────
        if ($this->matches($message, ['slow', 'quiet', 'dead', 'day', 'week'])) {
            $byDay = $bookings->groupBy(fn($b) => date('l', strtotime($b->date)));
            $slowest = $byDay->sortBy(fn($g) => $g->count())->keys()->first();
            return [
                'type' => 'text',
                'text' => "Based on your booking history, **{$slowest}** is your slowest day. Consider a **\"{$slowest} Special\"** promotion to drive volume on that day.",
            ];
        }

        // ── Fallback ─────────────────────────────────────────────────────────
        return [
            'type' => 'text',
            'text' => "I can help you with insights about your **revenue**, **bookings**, **customers**, **staff**, **services**, and **churn risk**. Try asking: _\"How is my business doing?\"_ or _\"Who are my top staff?\"_"
        ];
    }

    /**
     * Check if the message contains any of the given keywords.
     */
    private function matches(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) return true;
        }
        return false;
    }
}
