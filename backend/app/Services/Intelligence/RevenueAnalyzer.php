<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

class RevenueAnalyzer implements AnalyzerInterface
{
    public function analyze(Collection $transactions, Collection $bookings): array
    {
        $gross = $transactions->sum('gross_amount');
        $gatewayFees = $transactions->sum('gateway_fee');
        $platformFees = $transactions->sum('platform_fee');
        $refunds = 0; // TODO: Implement refunds when model supports it
        $net = $transactions->sum('net_amount');
        
        // Pending settlement - sum of net amount for transactions not yet settled
        $pending = $transactions->whereNull('settlement_id')->sum('net_amount');
        
        // Cash in hand is net minus pending (assuming settled goes to bank)
        $cashInHand = $net - $pending;

        $grossPct = 100;
        $gatewayPct = $gross > 0 ? round(($gatewayFees / $gross) * 100, 1) : 0;
        $platformPct = $gross > 0 ? round(($platformFees / $gross) * 100, 1) : 0;
        $refundsPct = $gross > 0 ? round(($refunds / $gross) * 100, 1) : 0;
        $netPct = $gross > 0 ? round(($net / $gross) * 100, 1) : 0;

        // Group by payment method
        $byChannel = [];
        $txByMethod = $transactions->groupBy('payment_method_id');
        foreach ($txByMethod as $methodId => $txs) {
            $method = $txs->first()->paymentMethod;
            $channelName = $method ? $method->display_name : 'Unknown';
            $channelFees = $txs->sum(fn($tx) => $tx->gateway_fee + $tx->platform_fee);
            $byChannel[] = [
                'channel' => $channelName,
                'fees' => $channelFees
            ];
        }

        return [
            'analytics' => [
                'revenue' => [
                    'gross' => $gross,
                    'gateway_fees' => $gatewayFees,
                    'platform_fees' => $platformFees,
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
                'revenue_trend' => $this->buildRevenueTrend($transactions),
            ]
        ];
    }

    private function buildRevenueTrend(Collection $transactions): array
    {
        return $transactions
            ->groupBy(fn($tx) => date('Y-m-d', strtotime($tx->created_at)))
            ->map(fn($group) => [
                'date' => date('M d', strtotime($group->first()->created_at)),
                'revenue' => $group->sum('gross_amount'),
                'net'     => $group->sum('net_amount'),
            ])
            ->sortKeys()
            ->values()
            ->toArray();
    }
}
