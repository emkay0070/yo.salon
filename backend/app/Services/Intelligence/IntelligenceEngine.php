<?php

namespace App\Services\Intelligence;

use App\Models\Salon;
use App\Models\Transaction;
use App\Models\Booking;
use Carbon\Carbon;

class IntelligenceEngine
{
    protected array $analyzers = [];
    protected RuleEngine $ruleEngine;
    protected BriefingBuilder $briefingBuilder;

    public function __construct()
    {
        // Compose the engine with its specialized analyzers
        $this->analyzers = [
            new RevenueAnalyzer(),
            new StaffAnalyzer(),
            new ChurnAnalyzer(),
            new ForecastService(),
        ];
        
        $this->ruleEngine = new RuleEngine();
        $this->briefingBuilder = new BriefingBuilder();
    }

    /**
     * Generate the complete Intelligence DTO for a salon.
     */
    public function generate(Salon $salon): array
    {
        $startTime = microtime(true);
        $now = Carbon::now();
        
        // Fetch raw data once
        $transactions = Transaction::where('salon_id', $salon->id)
            ->whereIn('status', ['completed', 'paid'])
            ->get();
            
        $bookings = Booking::with(['customer', 'staff', 'service'])
            ->where('salon_id', $salon->id)
            ->get();

        // Initialize the DTO
        $dto = [
            'schema_version' => 1,
            'generated_at' => $now->toIso8601String(),
            'data_through' => $now->copy()->subMinutes(1)->toIso8601String(),
            'confidence' => 0.96,
            'analytics' => [],
            'forecast' => [],
            'recommendations' => [],
            '_copilot_context' => []
        ];

        // 1. Run all analyzers and merge their results
        foreach ($this->analyzers as $analyzer) {
            $result = $analyzer->analyze($transactions, $bookings);
            
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

        // 3. Generate Executive Briefing
        $dto['briefing'] = $this->briefingBuilder->build($dto, $salon);
        
        // 4. Generate Recommendations (extract from signals & churn)
        $dto['recommendations'] = $this->extractRecommendations($dto);

        // 5. Populate Copilot Context
        $dto['_copilot_context'] = $this->buildCopilotContext($dto);

        // Calculate processing time
        $endTime = microtime(true);
        $dto['processing_ms'] = round(($endTime - $startTime) * 1000);

        return $dto;
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
