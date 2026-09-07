<?php

namespace App\Services\Finance;

use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Models\Salon;
use Illuminate\Support\Carbon;

class FinanceAnalyticsService
{
    /**
     * Get aggregate financial metrics for a given salon.
     */
    public function getSalonMetrics(Salon $salon): array
    {
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
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
     * Get today's financial metrics for a given salon.
     */
    public function getTodayMetrics(Salon $salon): array
    {
        $today = Carbon::today();
        
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
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
    public function getRevenueTrend(Salon $salon, int $days = 30): array
    {
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();
            
        $processingAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
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
     * @param Salon $salon
     * @return array
     */
    public function getIntelligenceFinancialFacts(Salon $salon): array
    {
        // 1. Salon-level totals (from Ledger - canonical)
        $totals = $this->getSalonMetrics($salon);
        $todayMetrics = $this->getTodayMetrics($salon);
        $trend = $this->getRevenueTrend($salon, 30);

        // 2. Revenue by booking (Ledger → JournalEntry → Booking join)
        $byBooking = $this->getRevenueByBooking($salon);

        // 3. Revenue by specialist (Ledger payable accounts from distribution)
        $bySpecialist = $this->getRevenueBySpecialist($salon);

        // 4. Revenue by customer (Ledger → JournalEntry → Booking → Customer join)
        $byCustomer = $this->getRevenueByCustomer($salon);

        // 5. Revenue by service (Ledger → JournalEntry → Booking → Service join)
        $byService = $this->getRevenueByService($salon);

        // 6. Pending settlements (from Settlement domain, not Ledger)
        $settlements = $this->getPendingSettlements($salon);

        return [
            'totals' => [
                'gross_revenue' => $totals['total_gross_revenue'],
                'net_revenue' => $totals['total_net_revenue'],
                'processing_fees' => $totals['total_fees'],
                'today_gross_revenue' => $todayMetrics['today_gross_revenue'],
                'today_net_revenue' => $todayMetrics['today_net_revenue'],
            ],
            'trend' => $trend,
            'by_booking' => $byBooking,
            'by_specialist' => $bySpecialist,
            'by_customer' => $byCustomer,
            'by_service' => $byService,
            'settlements' => $settlements,
        ];
    }

    /**
     * Get revenue breakdown by booking.
     * Joins Ledger → JournalEntry → Booking to attribute revenue to specific bookings.
     */
    protected function getRevenueByBooking(Salon $salon): array
    {
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        // Query payment journals (reference_type = Transaction)
        // Then join to Booking via Transaction.booking_id
        $revenueByBooking = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'credit')
            ->whereHas('journalEntry', function ($query) {
                $query->where('reference_type', \App\Models\Transaction::class);
            })
            ->whereHas('journalEntry.reference', function ($query) use ($salon) {
                $query->where('salon_id', $salon->id);
            })
            ->with(['journalEntry.reference' => function ($query) {
                $query->select('id', 'booking_id', 'salon_id');
            }])
            ->get()
            ->groupBy(function ($entry) {
                return $entry->journalEntry->reference->booking_id ?? null;
            })
            ->map(function ($entries, $bookingId) {
                $gross = $entries->sum('amount');
                
                // Get processing fees for this booking
                $processingAccount = LedgerAccount::where('owner_type', Salon::class)
                    ->where('owner_id', $entries->first()->ledgerAccount->owner_id)
                    ->where('account_type', 'processing_cost')
                    ->first();

                $fees = 0;
                if ($processingAccount) {
                    $fees = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                        ->where('type', 'debit')
                        ->whereHas('journalEntry', function ($query) use ($bookingId) {
                            $query->where('reference_type', \App\Models\Transaction::class)
                                ->whereHas('reference', function ($q) use ($bookingId) {
                                    $q->where('booking_id', $bookingId);
                                });
                        })
                        ->sum('amount');
                }

                return [
                    'booking_id' => $bookingId,
                    'gross_revenue' => $gross,
                    'processing_fees' => $fees,
                    'net_revenue' => $gross - $fees,
                ];
            })
            ->values()
            ->keyBy('booking_id')
            ->toArray();

        return $revenueByBooking;
    }

    /**
     * Get revenue breakdown by specialist.
     * Uses payable accounts from revenue_distribution journals.
     * Includes booking count for legacy analytics compatibility.
     */
    protected function getRevenueBySpecialist(Salon $salon): array
    {
        // Query payable accounts owned by specialists for this salon
        // This requires knowing which specialists belong to this salon
        $specialistIds = \App\Models\Specialist::where('salon_id', $salon->id)
            ->pluck('id')
            ->toArray();

        if (empty($specialistIds)) {
            return [];
        }

        $revenueBySpecialist = LedgerEntry::where('type', 'credit')
            ->whereHas('ledgerAccount', function ($query) use ($specialistIds) {
                $query->where('account_type', 'payable')
                    ->where('owner_type', \App\Models\Specialist::class)
                    ->whereIn('owner_id', $specialistIds);
            })
            ->with('ledgerAccount.owner')
            ->get()
            ->groupBy('ledger_account.owner_id')
            ->map(function ($entries, $specialistId) {
                $specialist = $entries->first()->ledgerAccount->owner;
                
                // Count bookings for this specialist
                $bookingCount = \App\Models\Booking::where('specialist_id', $specialistId)
                    ->where('salon_id', $entries->first()->ledgerAccount->owner_id)
                    ->count();
                
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
     */
    protected function getRevenueByCustomer(Salon $salon): array
    {
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        $revenueByCustomer = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'credit')
            ->whereHas('journalEntry', function ($query) {
                $query->where('reference_type', \App\Models\Transaction::class);
            })
            ->whereHas('journalEntry.reference', function ($query) use ($salon) {
                $query->where('salon_id', $salon->id);
            })
            ->with(['journalEntry.reference' => function ($query) {
                $query->select('id', 'booking_id', 'salon_id', 'customer_id');
            }])
            ->get()
            ->groupBy(function ($entry) {
                return $entry->journalEntry->reference->customer_id ?? null;
            })
            ->map(function ($entries, $customerId) {
                $gross = $entries->sum('amount');
                
                // Get processing fees for this customer's transactions
                $processingAccount = LedgerAccount::where('owner_type', Salon::class)
                    ->where('owner_id', $entries->first()->ledgerAccount->owner_id)
                    ->where('account_type', 'processing_cost')
                    ->first();

                $fees = 0;
                if ($processingAccount) {
                    $customerBookingIds = $entries->pluck('journalEntry.reference.booking_id')->filter()->unique();
                    $fees = LedgerEntry::where('ledger_account_id', $processingAccount->id)
                        ->where('type', 'debit')
                        ->whereHas('journalEntry', function ($query) use ($customerBookingIds) {
                            $query->where('reference_type', \App\Models\Transaction::class)
                                ->whereHas('reference', function ($q) use ($customerBookingIds) {
                                    $q->whereIn('booking_id', $customerBookingIds);
                                });
                        })
                        ->sum('amount');
                }

                return [
                    'customer_id' => $customerId,
                    'gross_revenue' => $gross,
                    'processing_fees' => $fees,
                    'net_revenue' => $gross - $fees,
                ];
            })
            ->values()
            ->keyBy('customer_id')
            ->toArray();

        return $revenueByCustomer;
    }

    /**
     * Get revenue breakdown by service.
     * Joins Ledger → JournalEntry → Booking → Service.
     * Includes booking count for legacy analytics compatibility.
     */
    protected function getRevenueByService(Salon $salon): array
    {
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        if (!$revenueAccount) {
            return [];
        }

        $revenueByService = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'credit')
            ->whereHas('journalEntry', function ($query) {
                $query->where('reference_type', \App\Models\Transaction::class);
            })
            ->whereHas('journalEntry.reference', function ($query) use ($salon) {
                $query->where('salon_id', $salon->id);
            })
            ->with(['journalEntry.reference' => function ($query) {
                $query->select('id', 'booking_id', 'salon_id', 'customer_id');
            }])
            ->get()
            ->map(function ($entry) {
                // Load booking to get service_id
                $booking = \App\Models\Booking::find($entry->journalEntry->reference->booking_id);
                return [
                    'service_id' => $booking->service_id ?? null,
                    'booking_id' => $booking->id ?? null,
                    'amount' => $entry->amount,
                ];
            })
            ->filter(function ($item) {
                return $item['service_id'] !== null;
            })
            ->groupBy('service_id')
            ->map(function ($entries, $serviceId) {
                $service = \App\Models\Service::find($serviceId);
                $bookingCount = $entries->pluck('booking_id')->filter()->unique()->count();
                
                return [
                    'service_id' => $serviceId,
                    'service_name' => $service->name ?? 'Unknown',
                    'revenue' => $entries->sum('amount'),
                    'booking_count' => $bookingCount,
                ];
            })
            ->values()
            ->keyBy('service_id')
            ->toArray();

        return $revenueByService;
    }

    /**
     * Get pending settlements for the salon.
     * Uses Settlement domain, not Ledger.
     */
    protected function getPendingSettlements(Salon $salon): array
    {
        $pendingSettlements = \App\Domain\Finance\Settlement\Settlement::where('payable_type', Salon::class)
            ->where('payable_id', $salon->id)
            ->where('status', \App\Domain\Finance\Settlement\Settlement::STATUS_PENDING)
            ->sum('amount');

        return [
            'pending_amount' => $pendingSettlements,
            'status' => 'pending',
        ];
    }
}
