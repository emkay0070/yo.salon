<?php

namespace App\Services\Intelligence;

use App\Models\Provider;
use App\Models\Location;
use App\Models\IntelligenceRun;
use Carbon\Carbon;

class IntelligenceSnapshotService
{
    /**
     * Get or create intelligence snapshot for a provider/location.
     *
     * Uses cached/persisted results if available and fresh enough (within 5 minutes).
     * Otherwise generates new intelligence and persists it.
     *
     * @param Provider $provider
     * @param Location|null $location
     * @param int $freshnessMinutes Maximum age of cached data in minutes
     * @return array
     */
    public function getSnapshot(Provider $provider, ?Location $location = null, int $freshnessMinutes = 5): array
    {
        // Use provider's configured timezone for freshness calculation
        $timezone = $provider->timezone ?? 'UTC';

        // Check for recent completed run
        $recentRun = IntelligenceRun::where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->when($location, fn($q) => $q->where('location_id', $location->id))
            ->where('generated_at', '>=', Carbon::now($timezone)->subMinutes($freshnessMinutes))
            ->orderBy('generated_at', 'desc')
            ->first();

        if ($recentRun) {
            $dto = $this->hydrateFromRun($recentRun);
            $dto['freshness'] = $this->calculateFreshness($recentRun->generated_at, $timezone);
            return $dto;
        }

        // No fresh snapshot exists, generate new intelligence
        return $this->generateAndPersist($provider, $location);
    }

    /**
     * Calculate freshness label based on age.
     *
     * @param Carbon $generatedAt
     * @param string $timezone
     * @return string
     */
    protected function calculateFreshness(Carbon $generatedAt, string $timezone): string
    {
        $now = Carbon::now($timezone);
        $minutesDiff = $now->diffInMinutes($generatedAt);

        if ($minutesDiff < 1) return 'realtime';
        if ($minutesDiff < 5) return 'fresh';
        if ($minutesDiff < 15) return 'recent';
        if ($minutesDiff < 60) return 'stale';
        return 'outdated';
    }

    /**
     * Generate new intelligence and persist it.
     *
     * @param Provider $provider
     * @param Location|null $location
     * @return array
     */
    protected function generateAndPersist(Provider $provider, ?Location $location = null): array
    {
        $intelligenceEngine = app(IntelligenceEngine::class);
        $dto = $intelligenceEngine->generateFresh($provider, $location);

        // Persist the run
        $run = IntelligenceRun::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'location_id' => $location ? $location->id : null,
            'analysis_type' => 'comprehensive',
            'schema_version' => $dto['schema_version'],
            'algorithm_version' => $dto['algorithm_version'] ?? 'v1',
            'generated_at' => Carbon::parse($dto['generated_at']),
            'data_through' => Carbon::parse($dto['data_through']),
            'status' => 'completed',
            'duration_ms' => $dto['processing_ms'] ?? null,
            'confidence' => $dto['confidence'],
            'metadata' => [
                'analytics_keys' => array_keys($dto['analytics']),
                'forecast_keys' => array_keys($dto['forecast']),
            ],
        ]);

        // Persist staff scores
        $staff = $dto['analytics']['staff'] ?? [];
        foreach ($staff as $staffMember) {
            $run->scores()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'provider_id' => $provider->id,
                'subject_type' => 'specialist',
                'subject_id' => $staffMember['id'],
                'score_type' => 'intelligence',
                'score' => $staffMember['intelligence_score'],
                'confidence' => $staffMember['confidence'] === 'high' ? 0.9 : ($staffMember['confidence'] === 'medium' ? 0.6 : 0.3),
                'sample_size' => $staffMember['sample_size'],
                'status' => $staffMember['confidence'],
                'score_breakdown' => $staffMember['score_breakdown'],
            ]);
        }

        // Persist insights/signals
        $signals = $dto['signals'] ?? [];
        foreach ($signals as $signal) {
            $run->insights()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'provider_id' => $provider->id,
                'type' => $signal['id'],
                'severity' => $signal['priority'],
                'title' => $signal['title'],
                'description' => $signal['summary'],
                'evidence' => ['impact' => $signal['impact']],
                'confidence' => 0.8, // Default confidence for rule-based signals
                'recommended_action' => $signal['recommended_action'],
                'deep_link' => $signal['deep_link'] ?? null,
                'is_predictive' => $signal['is_predictive'] ?? false,
            ]);
        }

        // Persist forecasts
        $projectedMonthlyNet = $dto['forecast']['projected_monthly_net'] ?? null;
        if ($projectedMonthlyNet !== null) {
            $endOfMonth = Carbon::now()->endOfMonth();
            $run->forecasts()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'provider_id' => $provider->id,
                'forecast_type' => 'monthly_revenue',
                'period' => 'monthly',
                'forecast_date' => $endOfMonth,
                'forecast_value' => $projectedMonthlyNet,
                'confidence' => $dto['forecast']['confidence'] === 'high' ? 0.8 : 0.5,
            ]);
        }

        return $dto;
    }

    /**
     * Hydrate DTO from persisted run.
     *
     * @param IntelligenceRun $run
     * @return array
     */
    protected function hydrateFromRun(IntelligenceRun $run): array
    {
        $dto = [
            'schema_version' => $run->schema_version,
            'algorithm_version' => $run->algorithm_version,
            'generated_at' => $run->generated_at->toIso8601String(),
            'data_through' => $run->data_through->toIso8601String(),
            'confidence' => (float) $run->confidence,
            'processing_ms' => $run->duration_ms,
            'analytics' => [],
            'forecast' => [],
            'signals' => [],
            'recommendations' => [],
            '_copilot_context' => [],
            '_from_cache' => true,
            '_cached_at' => $run->generated_at->toIso8601String(),
        ];

        // Hydrate staff scores
        $staffScores = $run->scores()
            ->where('subject_type', 'specialist')
            ->where('score_type', 'intelligence')
            ->get();

        $dto['analytics']['staff'] = $staffScores->map(function ($score) {
            return [
                'id' => $score->subject_id,
                'intelligence_score' => $score->score,
                'score_breakdown' => $score->score_breakdown,
                'confidence' => $score->status,
                'sample_size' => $score->sample_size,
            ];
        })->toArray();

        // Hydrate insights/signals
        $dto['signals'] = $run->insights->map(function ($insight) {
            return [
                'id' => $insight->type,
                'priority' => $insight->severity,
                'title' => $insight->title,
                'summary' => $insight->description,
                'impact' => $insight->evidence['impact'] ?? '',
                'recommended_action' => $insight->recommended_action,
                'deep_link' => $insight->deep_link,
                'is_predictive' => $insight->is_predictive,
            ];
        })->toArray();

        // Hydrate forecasts
        $monthlyForecast = $run->forecasts()
            ->where('forecast_type', 'monthly_revenue')
            ->where('period', 'monthly')
            ->first();

        if ($monthlyForecast) {
            $dto['forecast']['projected_monthly_net'] = (int) $monthlyForecast->forecast_value;
        }

        return $dto;
    }

    /**
     * Force regeneration of intelligence snapshot.
     *
     * @param Provider $provider
     * @param Location|null $location
     * @return array
     */
    public function regenerate(Provider $provider, ?Location $location = null): array
    {
        return $this->generateAndPersist($provider, $location);
    }
}
