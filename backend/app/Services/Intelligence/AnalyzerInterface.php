<?php

namespace App\Services\Intelligence;

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
     * PRINCIPLE: Intelligence interprets facts, it does not fetch facts.
     *
     * @param array $financialFacts From FinanceAnalyticsService::getIntelligenceFinancialFacts()
     * @param array $operationalFacts From OperationalAnalyticsService::getOperationalFacts()
     * @return array
     */
    public function analyze(array $financialFacts, array $operationalFacts): array;
}
