<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

interface AnalyzerInterface
{
    /**
     * Analyze using canonical domain facts.
     * 
     * IMPORTANT: Intelligence must NOT access Transaction, LedgerEntry, or Booking directly.
     * All financial facts must come from FinanceAnalyticsService via $financialFacts.
     * All operational facts must come from OperationalAnalyticsService via $operationalFacts.
     * This preserves the Finance → Intelligence and Operations → Intelligence boundaries.
     * 
     * @param array $financialFacts From FinanceAnalyticsService::getIntelligenceFinancialFacts()
     * @param array $operationalFacts From OperationalAnalyticsService::getOperationalFacts()
     * @param Collection $bookings Operational data (TODO: eliminate this parameter)
     * @return array
     */
    public function analyze(array $financialFacts, array $operationalFacts, Collection $bookings): array;
}
