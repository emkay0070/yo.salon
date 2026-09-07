<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

class RevenueAnalyzer implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts, Collection $bookings): array
    {
        // Use canonical financial facts from FinanceAnalyticsService
        $totals = $financialFacts['totals'];
        $trend = $financialFacts['trend'];
        $settlements = $financialFacts['settlements'];

        $gross = $totals['gross_revenue'];
        $net = $totals['net_revenue'];
        $processingFees = $totals['processing_fees'];
        
        // Pending settlement from Settlement domain (not Transaction)
        $pending = $settlements['pending_amount'];
        
        // Cash in hand is net minus pending
        $cashInHand = $net - $pending;

        // Refunds not currently tracked separately in Ledger
        // Would need to be added to FinanceAnalyticsService if needed
        $refunds = 0;

        $grossPct = 100;
        $gatewayPct = $gross > 0 ? round(($processingFees / $gross) * 100, 1) : 0;
        $platformPct = 0; // Not posted to Ledger in current implementation
        $refundsPct = $gross > 0 ? round(($refunds / $gross) * 100, 1) : 0;
        $netPct = $gross > 0 ? round(($net / $gross) * 100, 1) : 0;

        // Payment channel breakdown not available from Ledger
        // Would need to be added to FinanceAnalyticsService if needed
        $byChannel = [];

        return [
            'analytics' => [
                'revenue' => [
                    'gross' => $gross,
                    'gateway_fees' => $processingFees, // Ledger only tracks gateway fees
                    'platform_fees' => 0, // Not posted to Ledger
                    'refunds' => $refunds,
                    'net' => $net,
                    'settlement_pending' => $pending,
                    'cash_in_hand' => $cashInHand,
                ],
                'fees' => [
                    'gross_pct' => $grossPct,
                    'gateway_pct' => $gatewayPct,
                    'platform_pct' => $platformPct,
                    'refunds_pct' => $refundsPct,
                    'net_pct' => $netPct,
                    'by_channel' => $byChannel,
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
