<?php

namespace App\Services\Intelligence;

class RevenueAnalyzer implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts): array
    {
        // Use canonical financial facts from FinanceAnalyticsService
        $totals = $financialFacts['totals'];
        $trend = $financialFacts['trend'];
        $settlements = $financialFacts['settlements'];

        $gross = $totals['gross_revenue'];
        $net = $totals['net_revenue'];
        $processingFees = $totals['processing_fees'];
        $platformFees = $totals['platform_fees'] ?? null;
        $refunds = $totals['refunds'] ?? null;

        // Pending settlement from Settlement domain (not Transaction)
        $pending = $settlements['pending_amount'];

        // Cash in hand is net minus pending
        $cashInHand = $net - $pending;

        $grossPct = 100;
        $gatewayPct = $gross > 0 ? round(($processingFees / $gross) * 100, 1) : 0;
        $platformPct = ($platformFees !== null && $gross > 0) ? round(($platformFees / $gross) * 100, 1) : null;
        $refundsPct = ($refunds !== null && $gross > 0) ? round(($refunds / $gross) * 100, 1) : null;
        $netPct = $gross > 0 ? round(($net / $gross) * 100, 1) : 0;

        // Payment channel breakdown not available from Ledger
        $byChannel = $financialFacts['payment_channels'] ?? null;

        return [
            'analytics' => [
                'revenue' => [
                    'gross' => $gross,
                    'gateway_fees' => $processingFees,
                    'platform_fees' => $platformFees, // null if not tracked
                    'refunds' => $refunds, // null if not tracked
                    'net' => $net,
                    'settlement_pending' => $pending,
                    'cash_in_hand' => $cashInHand,
                ],
                'fees' => [
                    'gross_pct' => $grossPct,
                    'gateway_pct' => $gatewayPct,
                    'platform_pct' => $platformPct, // null if not tracked
                    'refunds_pct' => $refundsPct, // null if not tracked
                    'net_pct' => $netPct,
                    'by_channel' => $byChannel, // null if not tracked
                ],
                'revenue_trend' => $this->buildRevenueTrend($trend),
            ]
        ];
    }

    private function buildRevenueTrend(array $trend): array
    {
        // Transform FinanceAnalyticsService trend format to Intelligence format
        return collect($trend)
            ->map(function ($day) {
                return [
                    'date' => date('M d', strtotime($day['date'])),
                    'revenue' => $day['revenue'],
                    'net' => $day['net_revenue'],
                ];
            })
            ->toArray();
    }
}
