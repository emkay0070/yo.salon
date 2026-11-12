<?php

namespace App\Services\Finance;

use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Models\Provider;
use App\Models\Location;
use Illuminate\Support\Carbon;

class FinanceAnalyticsService
{
    /**
     * Get aggregate financial metrics for a given provider.
     */
    public function getProviderMetrics(Provider $provider): array
    {
        $providerId = $provider->id;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'processing_cost')
            ->first();

        // Calculate gross revenue (all credits to revenue account minus debits/refunds)
        $grossRevenue = 0;
        if ($revenueAccount) {
            $credits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'credit')->sum('amount');
            $debits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'debit')->sum('amount');
            $grossRevenue = $credits - $debits;
        }

        // Calculate total fees
        $totalFees = 0;
        if ($processingAccount) {
            $debits = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                ->where('type', 'debit')->sum('amount');
            $credits = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                ->where('type', 'credit')->sum('amount');
            $totalFees = $debits - $credits;
        }

        $netRevenue = $grossRevenue - $totalFees;

        return [
            'total_gross_revenue' => $grossRevenue,
            'total_net_revenue'   => $netRevenue,
            'total_fees'          => $totalFees,
        ];
    }

    /**
     * Get today's financial metrics for a given provider.
     */
    public function getTodayMetrics(Provider $provider): array
    {
        $today = Carbon::today();
        $providerId = $provider->id;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'processing_cost')
            ->first();

        $grossRevenue = 0;
        if ($revenueAccount) {
            $credits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'credit')
                ->whereDate('created_at', $today)
                ->sum('amount');
            $debits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'debit')
                ->whereDate('created_at', $today)
                ->sum('amount');
            $grossRevenue = $credits - $debits;
        }

        $totalFees = 0;
        if ($processingAccount) {
            $debits = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                ->where('type', 'debit')
                ->whereDate('created_at', $today)
                ->sum('amount');
            $credits = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                ->where('type', 'credit')
                ->whereDate('created_at', $today)
                ->sum('amount');
            $totalFees = $debits - $credits;
        }

        $netRevenue = $grossRevenue - $totalFees;

        return [
            'today_gross_revenue' => $grossRevenue,
            'today_net_revenue'   => $netRevenue,
        ];
    }

    /**
     * Get revenue trend for the last 30 days based on LedgerEntry
     */
    public function getRevenueTrend(Provider $provider, int $days = 30): array
    {
        $providerId = $provider->id;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();
            
        $processingAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'processing_cost')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        $startDate = Carbon::now()->subDays($days)->startOfDay();

        // Get daily gross revenue (credits - debits)
        $revenueEntries = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(function ($entry) {
                return $entry->created_at->format('Y-m-d');
            });

        // Get daily fees
        $feeEntries = collect();
        if ($processingAccount) {
            $feeEntries = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                ->where('created_at', '>=', $startDate)
                ->get()
                ->groupBy(function ($entry) {
                    return $entry->created_at->format('Y-m-d');
                });
        }

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $dateStr = Carbon::now()->subDays($days - 1 - $i)->format('Y-m-d');
            
            $dayRevenues = $revenueEntries->get($dateStr, collect());
            $dayGross = $dayRevenues->where('type', 'credit')->sum('amount') 
                      - $dayRevenues->where('type', 'debit')->sum('amount');
                      
            $dayFeesCol = $feeEntries->get($dateStr, collect());
            $dayFees = $dayFeesCol->where('type', 'debit')->sum('amount') 
                     - $dayFeesCol->where('type', 'credit')->sum('amount');
                     
            $dayNet = $dayGross - $dayFees;

            // Only add if there was activity (or keep 0 for chart continuity)
            $trend[] = [
                'date'        => $dateStr,
                'revenue'     => $dayGross,
                'net_revenue' => $dayNet,
            ];
        }

        return $trend;
    }

    /**
     * Get comprehensive financial facts for Intelligence Engine.
     *
     * This method builds a single analytical projection containing all financial
     * facts Intelligence needs, avoiding N+1 queries and preserving the
     * Finance → Intelligence boundary.
     *
     * IMPORTANT: Ledger net revenue = gross - gateway_fee (processing_cost account).
     * Transaction platform_fee and tax_amount are NOT posted to Ledger in current implementation.
     * Intelligence should NOT invent these from Transaction - use Ledger truth only.
     *
     * FACT CONTRACT PRINCIPLE: Return null for untracked data, not 0.
     * This allows Intelligence to distinguish between "zero" and "unknown".
     *
     * @param Provider $provider
     * @param Location|null $location
     * @return array
     */
    public function getIntelligenceFinancialFacts(Provider $provider, ?Location $location = null): array
    {
        // 1. Provider-level totals (from Ledger - canonical)
        $totals = $this->getProviderMetrics($provider);
        $todayMetrics = $this->getTodayMetrics($provider);
        $trend = $this->getRevenueTrend($provider, 30);

        // 2. Revenue by booking (Ledger → JournalEntry → Booking join)
        $byBooking = $this->getRevenueByBooking($provider, $location);

        // 3. Revenue by specialist (Ledger payable accounts from distribution)
        $bySpecialist = $this->getRevenueBySpecialist($provider, $location);

        // 4. Revenue by customer (Ledger → JournalEntry → Booking → Customer join)
        $byCustomer = $this->getRevenueByCustomer($provider, $location);

        // 5. Revenue by service (Ledger → JournalEntry → Booking → Service join)
        $byService = $this->getRevenueByService($provider, $location);

        // 6. Pending settlements (from Settlement domain, not Ledger)
        $settlements = $this->getPendingSettlements($provider);

        return [
            'totals' => [
                'gross_revenue' => $totals['total_gross_revenue'],
                'net_revenue' => $totals['total_net_revenue'],
                'processing_fees' => $totals['total_fees'],
                'platform_fees' => null, // Not tracked in Ledger
                'refunds' => null, // Not tracked in Ledger
                'today_gross_revenue' => $todayMetrics['today_gross_revenue'],
                'today_net_revenue' => $todayMetrics['today_net_revenue'],
            ],
            'trend' => $trend,
            'by_booking' => $byBooking,
            'by_specialist' => $bySpecialist,
            'by_customer' => $byCustomer,
            'by_service' => $byService,
            'settlements' => $settlements,
            'payment_channels' => null, // Not available from Ledger
        ];
    }

    /**
     * Get revenue breakdown by booking.
     * Joins Ledger → JournalEntry → Booking to attribute revenue to specific bookings.
     * Uses single aggregated query to avoid N+1.
     */
    protected function getRevenueByBooking(Provider $provider, ?Location $location = null): array
    {
        $providerId = $provider->id;
        $locationId = $location ? $location->id : null;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'processing_cost')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        // Get all revenue entries with booking_id in a single query
        $revenueByBooking = \DB::table('ledger_entries as le')
            ->join('journal_entries as je', 'le.journal_entry_id', '=', 'je.id')
            ->join('transactions as t', function ($join) {
                $join->on('je.reference_id', '=', 't.id')
                      ->where('je.reference_type', '=', \App\Models\Transaction::class);
            })
            ->join('bookings as b', 't.booking_id', '=', 'b.id')
            ->where('le.ledger_account_id', $revenueAccount->id)
            ->where('le.type', 'credit')
            ->when($locationId, fn($q) => $q->where('b.location_id', $locationId))
            ->select('b.id as booking_id', \DB::raw('SUM(le.amount) as gross_revenue'))
            ->groupBy('b.id')
            ->get()
            ->keyBy('booking_id')
            ->map(function ($item) {
                return [
                    'booking_id' => $item->booking_id,
                    'gross_revenue' => (int) $item->gross_revenue,
                    'processing_fees' => 0,
                    'net_revenue' => (int) $item->gross_revenue,
                ];
            })
            ->toArray();

        // Get all processing fees by booking in a single query
        if ($processingAccount) {
            $feesByBooking = \DB::table('ledger_entries as le')
                ->join('journal_entries as je', 'le.journal_entry_id', '=', 'je.id')
                ->join('transactions as t', function ($join) {
                    $join->on('je.reference_id', '=', 't.id')
                          ->where('je.reference_type', '=', \App\Models\Transaction::class);
                })
                ->join('bookings as b', 't.booking_id', '=', 'b.id')
                ->where('le.ledger_account_id', $processingAccount->id)
                ->where('le.type', 'debit')
                ->when($locationId, fn($q) => $q->where('b.location_id', $locationId))
                ->select('b.id as booking_id', \DB::raw('SUM(le.amount) as processing_fees'))
                ->groupBy('b.id')
                ->get()
                ->keyBy('booking_id');

            // Merge fees into revenue data
            foreach ($revenueByBooking as $bookingId => &$data) {
                $fees = $feesByBooking->get($bookingId);
                $data['processing_fees'] = $fees ? (int) $fees->processing_fees : 0;
                $data['net_revenue'] = $data['gross_revenue'] - $data['processing_fees'];
            }
        }

        return $revenueByBooking;
    }

    /**
     * Get revenue breakdown by specialist.
     * Uses payable accounts from revenue_distribution journals.
     * Includes booking count for legacy analytics compatibility.
     */
    protected function getRevenueBySpecialist(Provider $provider, ?Location $location = null): array
    {
        // Query payable accounts owned by specialists for this provider
        // This requires knowing which specialists belong to this provider
        $specialistIds = $provider->specialists()->pluck('specialists.id')->toArray();

        if (empty($specialistIds)) {
            return [];
        }

        $locationId = $location ? $location->id : null;

        $revenueBySpecialist = LedgerEntry::where('type', 'credit')
            ->whereHas('ledgerAccount', function ($query) use ($specialistIds) {
                $query->where('account_type', 'payable')
                    ->where('owner_type', \App\Models\Specialist::class)
                    ->whereIn('owner_id', $specialistIds);
            })
            ->with('ledgerAccount.owner')
            ->get()
            ->groupBy('ledger_account.owner_id')
            ->map(function ($entries, $specialistId) use ($provider, $locationId) {
                $specialist = $entries->first()->ledgerAccount->owner;
                
                // Count bookings for this specialist
                $bookingQuery = \App\Models\Booking::where('specialist_id', $specialistId)
                    ->where('provider_id', $provider->id);
                
                if ($locationId) {
                    $bookingQuery->where('location_id', $locationId);
                }
                
                $bookingCount = $bookingQuery->count();
                
                return [
                    'specialist_id' => $specialistId,
                    'specialist_name' => $specialist->name ?? 'Unknown',
                    'revenue' => $entries->sum('amount'),
                    'booking_count' => $bookingCount,
                ];
            })
            ->values()
            ->keyBy('specialist_id')
            ->toArray();

        return $revenueBySpecialist;
    }

    /**
     * Get revenue breakdown by customer.
     * Joins Ledger → JournalEntry → Booking → Customer.
     * Uses single aggregated query to avoid N+1.
     */
    protected function getRevenueByCustomer(Provider $provider, ?Location $location = null): array
    {
        $providerId = $provider->id;
        $locationId = $location ? $location->id : null;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'processing_cost')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        // Get all revenue by customer in a single query
        $revenueByCustomer = \DB::table('ledger_entries as le')
            ->join('journal_entries as je', 'le.journal_entry_id', '=', 'je.id')
            ->join('transactions as t', function ($join) {
                $join->on('je.reference_id', '=', 't.id')
                      ->where('je.reference_type', '=', \App\Models\Transaction::class);
            })
            ->join('bookings as b', 't.booking_id', '=', 'b.id')
            ->where('le.ledger_account_id', $revenueAccount->id)
            ->where('le.type', 'credit')
            ->when($locationId, fn($q) => $q->where('b.location_id', $locationId))
            ->whereNotNull('b.customer_id')
            ->select('b.customer_id', \DB::raw('SUM(le.amount) as gross_revenue'))
            ->groupBy('b.customer_id')
            ->get()
            ->keyBy('customer_id')
            ->map(function ($item) {
                return [
                    'customer_id' => $item->customer_id,
                    'gross_revenue' => (int) $item->gross_revenue,
                    'processing_fees' => 0,
                    'net_revenue' => (int) $item->gross_revenue,
                ];
            })
            ->toArray();

        // Get all processing fees by customer in a single query
        if ($processingAccount) {
            $feesByCustomer = \DB::table('ledger_entries as le')
                ->join('journal_entries as je', 'le.journal_entry_id', '=', 'je.id')
                ->join('transactions as t', function ($join) {
                    $join->on('je.reference_id', '=', 't.id')
                          ->where('je.reference_type', '=', \App\Models\Transaction::class);
                })
                ->join('bookings as b', 't.booking_id', '=', 'b.id')
                ->where('le.ledger_account_id', $processingAccount->id)
                ->where('le.type', 'debit')
                ->when($locationId, fn($q) => $q->where('b.location_id', $locationId))
                ->whereNotNull('b.customer_id')
                ->select('b.customer_id', \DB::raw('SUM(le.amount) as processing_fees'))
                ->groupBy('b.customer_id')
                ->get()
                ->keyBy('customer_id');

            // Merge fees into revenue data
            foreach ($revenueByCustomer as $customerId => &$data) {
                $fees = $feesByCustomer->get($customerId);
                $data['processing_fees'] = $fees ? (int) $fees->processing_fees : 0;
                $data['net_revenue'] = $data['gross_revenue'] - $data['processing_fees'];
            }
        }

        return $revenueByCustomer;
    }

    /**
     * Get revenue breakdown by service.
     * Joins Ledger → JournalEntry → Booking → Service.
     * Includes booking count for legacy analytics compatibility.
     * Uses single aggregated query to avoid N+1.
     */
    protected function getRevenueByService(Provider $provider, ?Location $location = null): array
    {
        $providerId = $provider->id;
        $locationId = $location ? $location->id : null;
        
        $revenueAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $providerId)
            ->where('account_type', 'revenue')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        // Get all revenue by service in a single query
        $revenueByService = \DB::table('ledger_entries as le')
            ->join('journal_entries as je', 'le.journal_entry_id', '=', 'je.id')
            ->join('transactions as t', function ($join) {
                $join->on('je.reference_id', '=', 't.id')
                      ->where('je.reference_type', '=', \App\Models\Transaction::class);
            })
            ->join('bookings as b', 't.booking_id', '=', 'b.id')
            ->where('le.ledger_account_id', $revenueAccount->id)
            ->where('le.type', 'credit')
            ->when($locationId, fn($q) => $q->where('b.location_id', $locationId))
            ->whereNotNull('b.service_id')
            ->select('b.service_id', \DB::raw('SUM(le.amount) as revenue'), \DB::raw('COUNT(DISTINCT b.id) as booking_count'))
            ->groupBy('b.service_id')
            ->get()
            ->keyBy('service_id')
            ->map(function ($item) {
                $service = \App\Models\Service::find($item->service_id);
                return [
                    'service_id' => $item->service_id,
                    'service_name' => $service->name ?? 'Unknown',
                    'revenue' => (int) $item->revenue,
                    'booking_count' => (int) $item->booking_count,
                ];
            })
            ->toArray();

        return $revenueByService;
    }

    /**
     * Get pending settlements for the provider.
     * Uses Settlement domain, not Ledger.
     */
    protected function getPendingSettlements(Provider $provider): array
    {
        $providerId = $provider->id;
        
        $pendingSettlements = \App\Domain\Finance\Settlement\Settlement::where('provider_id', $providerId)
            ->where('status', \App\Domain\Finance\Settlement\Settlement::STATUS_PENDING)
            ->sum('amount');

        return [
            'pending_amount' => $pendingSettlements,
            'status' => 'pending',
        ];
    }
}
