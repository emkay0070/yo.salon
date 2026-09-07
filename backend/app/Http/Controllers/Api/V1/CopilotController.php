<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Services\Intelligence\IntelligenceEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    protected IntelligenceEngine $intelligenceEngine;

    public function __construct(IntelligenceEngine $intelligenceEngine)
    {
        $this->intelligenceEngine = $intelligenceEngine;
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
            // Use canonical Intelligence Engine for business context
            $salon = auth()->user()->currentSalon();
            if (!$salon) {
                return response()->json(['error' => 'Salon not found'], 404);
            }
            
            $intelligenceDto = $this->intelligenceEngine->generate($salon);
            $response = $this->matchIntent($message, $intelligenceDto);
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
     * Deterministic NLP intent matcher using canonical Intelligence Engine data.
     */
    private function matchIntent(string $message, array $intelligenceDto): array
    {
        $financialFacts = $intelligenceDto['financial_facts'] ?? [];
        $operationalFacts = $intelligenceDto['operational_facts'] ?? [];
        
        $totalGross = $financialFacts['totals']['gross_revenue'] ?? 0;
        $totalNet = $financialFacts['totals']['net_revenue'] ?? 0;
        $totalFees = $financialFacts['totals']['processing_fees'] ?? 0;
        $totalBookings = $operationalFacts['totals']['total_bookings'] ?? 0;
        $uniqueCustomers = count($operationalFacts['by_customer'] ?? []);

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
            $todayBookings = $operationalFacts['today']['new_bookings_today'] ?? 0;
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
            // Use Intelligence Engine signals for churn risk
            $churnSignals = collect($intelligenceDto['signals'] ?? [])
                ->filter(fn($s) => str_contains(strtolower($s['title'] ?? ''), 'churn'));
            
            if ($churnSignals->count() > 0) {
                $signal = $churnSignals->first();
                return [
                    'type' => 'warning',
                    'text' => "⚠️ **Churn Risk Detected:** " . ($signal['description'] ?? 'Some customers are at risk.'),
                    'data' => [['label' => 'Risk Level', 'value' => 'Detected']]
                ];
            } else {
                return ['type' => 'success', 'text' => "✅ No significant churn risk detected. Your regular customers are coming back consistently!"];
            }
        }

        // ── Intent: Top Service ──────────────────────────────────────────────
        if ($this->matches($message, ['service', 'popular', 'best', 'top', 'performing'])) {
            $byService = $operationalFacts['by_service'] ?? [];
            if (!empty($byService)) {
                $topService = collect($byService)->sortByDesc('booking_count')->first();
                if ($topService) {
                    return [
                        'type' => 'metric',
                        'text' => "Your **top performing service** is **{$topService['service_name']}** with **{$topService['booking_count']} bookings**.",
                        'data' => [
                            ['label' => 'Service',  'value' => $topService['service_name']],
                            ['label' => 'Bookings', 'value' => $topService['booking_count']],
                        ]
                    ];
                }
            }
        }

        // ── Intent: Staff ────────────────────────────────────────────────────
        if ($this->matches($message, ['staff', 'employee', 'who is', 'best stylist', 'team'])) {
            $bySpecialist = $operationalFacts['by_specialist'] ?? [];
            if (!empty($bySpecialist)) {
                $topSpecialist = collect($bySpecialist)->sortByDesc('booking_count')->first();
                if ($topSpecialist) {
                    return [
                        'type' => 'success',
                        'text' => "Your **top performing team member** is **{$topSpecialist['specialist_name']}** who has handled **{$topSpecialist['booking_count']} bookings**.",
                        'data' => [
                            ['label' => 'Staff Member', 'value' => $topSpecialist['specialist_name']],
                            ['label' => 'Bookings',     'value' => $topSpecialist['booking_count']],
                        ]
                    ];
                }
            }
        }

        // ── Intent: Health / Overview ────────────────────────────────────────
        if ($this->matches($message, ['how', 'doing', 'health', 'overview', 'summary', 'status', 'performance'])) {
            $summary = $intelligenceDto['briefing']['narrative'] ?? 'No summary available.';
            return ['type' => 'text', 'text' => $summary];
        }

        // ── Intent: Slow Days ────────────────────────────────────────────────
        if ($this->matches($message, ['slow', 'quiet', 'dead', 'day', 'week'])) {
            $weeklyBookings = $operationalFacts['temporal']['weekly_bookings'] ?? [];
            if (!empty($weeklyBookings)) {
                $slowest = collect($weeklyBookings)->sortBy('bookings')->keys()->first();
                return [
                    'type' => 'text',
                    'text' => "Based on your booking history, **{$slowest}** is your slowest day. Consider a **\"{$slowest} Special\"** promotion to drive volume on that day.",
                ];
            }
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
