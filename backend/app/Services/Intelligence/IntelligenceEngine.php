<?php

namespace App\Services\Intelligence;

use App\Models\Provider;
use App\Models\Location;
use App\Models\Booking;
use App\Services\Finance\FinanceAnalyticsService;
use App\Services\OperationalAnalyticsService;
use Carbon\Carbon;

class IntelligenceEngine
{
    protected array $analyzers = [];
    protected RuleEngine $ruleEngine;
    protected BriefingBuilder $briefingBuilder;
    protected FinanceAnalyticsService $financeAnalyticsService;
    protected OperationalAnalyticsService $operationalAnalyticsService;

    public function __construct(
        FinanceAnalyticsService $financeAnalyticsService,
        OperationalAnalyticsService $operationalAnalyticsService
    ) {
        // Compose the engine with its specialized analyzers
        $this->analyzers = [
            new RevenueAnalyzer(),
            new StaffAnalyzer(),
            new ChurnAnalyzer(),
            new ForecastService(),
        ];
        
        $this->ruleEngine = new RuleEngine();
        $this->briefingBuilder = new BriefingBuilder();
        $this->financeAnalyticsService = $financeAnalyticsService;
        $this->operationalAnalyticsService = $operationalAnalyticsService;
    }

    /**
     * Generate the complete Intelligence DTO for a provider/location.
     *
     * IMPORTANT: Intelligence receives canonical facts from two domains:
     * - Financial facts from FinanceAnalyticsService (Finance → Intelligence boundary)
     * - Operational facts from OperationalAnalyticsService (Operations → Intelligence boundary)
     *
     * This preserves the domain separation and ensures Intelligence never queries
     * Transaction, LedgerEntry, or Booking directly for analytics.
     *
     * PRINCIPLE: Intelligence interprets facts, it does not fetch facts.
     *
     * Uses IntelligenceSnapshotService for caching/persistence to avoid expensive recalculations.
     */
    public function generate(Provider $provider, ?Location $location = null, bool $forceRegenerate = false): array
    {
        $snapshotService = app(IntelligenceSnapshotService::class);

        if ($forceRegenerate) {
            return $snapshotService->regenerate($provider, $location);
        }

        return $snapshotService->getSnapshot($provider, $location);
    }

    /**
     * Generate fresh intelligence without caching.
     * Used by IntelligenceSnapshotService for persistence.
     *
     * @param Provider $provider
     * @param Location|null $location
     * @return array
     */
    public function generateFresh(Provider $provider, ?Location $location = null): array
    {
        $startTime = microtime(true);

        // Use provider's configured timezone, fallback to UTC
        $timezone = $provider->timezone ?? 'UTC';
        $now = Carbon::now($timezone);

        // Fetch canonical financial facts from Finance domain
        $financialFacts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($provider, $location);

        // Fetch canonical operational facts from Operations domain
        $operationalFacts = $this->operationalAnalyticsService->getOperationalFacts($provider, $location);

        // Initialize the DTO
        $dto = [
            'schema_version' => 1,
            'algorithm_version' => 'v1',
            'generated_at' => $now->toIso8601String(),
            'data_through' => $now->copy()->subMinutes(1)->toIso8601String(),
            'timezone' => $timezone,
            'freshness' => '1_minute',
            'confidence' => 0.96,
            'analytics' => [],
            'forecast' => [],
            'recommendations' => [],
            '_copilot_context' => []
        ];

        // 1. Run all analyzers with canonical facts only (no direct database queries)
        foreach ($this->analyzers as $analyzer) {
            $result = $analyzer->analyze($financialFacts, $operationalFacts);

            // Deep merge the result into the DTO
            foreach ($result as $key => $value) {
                if (is_array($value) && isset($dto[$key]) && is_array($dto[$key])) {
                    $dto[$key] = array_merge_recursive($dto[$key], $value);
                } else {
                    $dto[$key] = $value;
                }
            }
        }

        // 2. Generate Prioritized Signals
        $dto['signals'] = $this->ruleEngine->generateSignals($dto);

        // 3. Add Today's Operational Context
        $dto['analytics']['today'] = $this->getTodayContext($operationalFacts, $financialFacts);

        // 4. Generate Executive Briefing
        $dto['briefing'] = $this->briefingBuilder->build($dto, $provider, $location);

        // 4. Generate Recommendations (extract from signals & churn)
        $dto['recommendations'] = $this->extractRecommendations($dto);

        // 5. Populate Copilot Context
        $dto['_copilot_context'] = $this->buildCopilotContext($dto);

        // Calculate processing time
        $endTime = microtime(true);
        $dto['processing_ms'] = round(($endTime - $startTime) * 1000);

        return $dto;
    }
    
    private function getTodayContext(array $operationalFacts, array $financialFacts): array
    {
        $today = Carbon::today();
        $todayDate = $today->toDateString();

        // Get today's bookings from operational facts
        $todayStats = $operationalFacts['today'] ?? [];

        // Get today's revenue from financial facts
        $trend = $financialFacts['trend'] ?? [];
        $todayRevenue = collect($trend)
            ->firstWhere('date', $todayDate);
        $todayNetRevenue = $todayRevenue['net_revenue'] ?? 0;

        // Count active specialists (those with bookings today)
        $bySpecialist = $operationalFacts['by_specialist'] ?? [];
        $activeSpecialists = collect($bySpecialist)
            ->where('booking_count', '>', 0)
            ->count();

        return [
            'appointments' => $todayStats['total_bookings'] ?? 0,
            'revenue' => $todayNetRevenue,
            'specialists' => $activeSpecialists,
            'date' => $todayDate
        ];
    }

    private function extractRecommendations(array $dto): array
    {
        $recommendations = [];

        // Add churn recommendations
        $churnRisks = $dto['analytics']['customers']['churn_risks'] ?? [];
        foreach ($churnRisks as $risk) {
            if ($risk['risk_label'] === 'critical') {
                $recommendations[] = [
                    'id' => 'churn_reengage_' . $risk['customer_id'],
                    'title' => "Re-engage {$risk['name']}",
                    'body' => "They haven't visited in {$risk['days_since_visit']} days. A personalised message today has a high chance of bringing them back.",
                    'impact' => "UGX " . number_format($risk['lifetime_value']) . " LTV at risk",
                    'action' => 'Send WhatsApp',
                    'customer_id' => $risk['customer_id']
                ];
            }
        }

        return $recommendations;
    }
    
    private function buildCopilotContext(array $dto): array
    {
        $context = [
            'signal_ids' => collect($dto['signals'] ?? [])->pluck('id')->toArray(),
            'churn_customer_ids' => collect($dto['analytics']['customers']['churn_risks'] ?? [])->pluck('customer_id')->toArray(),
            'top_staff_id' => null,
            'slowest_day' => null
        ];
        
        $staff = $dto['analytics']['staff'] ?? [];
        if (!empty($staff)) {
            $context['top_staff_id'] = $staff[0]['id'];
        }
        
        $demandByDay = $dto['analytics']['demand']['by_day_of_week'] ?? [];
        if (!empty($demandByDay)) {
            $sortedByIntensity = collect($demandByDay)->sortBy('intensity')->values();
            $context['slowest_day'] = $sortedByIntensity->first()['day'] ?? null;
        }
        
        return $context;
    }
}
