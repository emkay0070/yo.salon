<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGoal;
use App\Models\JourneyMilestoneAchievement;

class JourneyQueryService
{
    private JourneyMetricCalculator $calculator;
    private JourneyMetricRepository $repository;
    private JourneyScopeResolver $scopeResolver;
    private JourneyGoalService $goalService;
    private JourneyMilestoneService $milestoneService;
    private JourneyGrowthOpportunityService $opportunityService;

    public function __construct(
        JourneyMetricCalculator $calculator,
        JourneyMetricRepository $repository,
        JourneyScopeResolver $scopeResolver,
        JourneyGoalService $goalService,
        JourneyMilestoneService $milestoneService,
        JourneyGrowthOpportunityService $opportunityService
    ) {
        $this->calculator = $calculator;
        $this->repository = $repository;
        $this->scopeResolver = $scopeResolver;
        $this->goalService = $goalService;
        $this->milestoneService = $milestoneService;
        $this->opportunityService = $opportunityService;
    }

    /**
     * Assemble complete Journey response
     * Controller stays thin - this service orchestrates
     */
    public function getJourney(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        // Validate scope
        $scope = $this->scopeResolver->resolve($specialist, $assignment);

        // Calculate and store metrics (separate layers)
        $metrics = $this->calculator->calculate($specialist, $assignment);
        $this->repository->store($specialist, $assignment, $metrics);

        // Update goal progress from authoritative metrics
        $goals = JourneyGoal::where('specialist_id', $specialist->id)
            ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
            ->whereIn('status', ['DRAFT', 'ACTIVE', 'PAUSED'])
            ->get();

        foreach ($goals as $goal) {
            $this->goalService->updateProgress($goal);
        }

        // Check for new milestones
        $this->milestoneService->checkForMilestones($specialist, $assignment);

        // Generate growth opportunities
        $opportunities = $this->opportunityService->generateOpportunities($specialist, $assignment);

        // Assemble response
        return [
            'growth_snapshot' => $metrics,
            'goals' => $goals->map(fn($goal) => [
                'id' => $goal->id,
                'title' => $goal->title,
                'goal_type' => $goal->goal_type,
                'current_value' => $goal->current_value,
                'target_value' => $goal->target_value,
                'unit' => $goal->unit,
                'deadline' => $goal->deadline?->format('Y-m-d'),
                'status' => $goal->status,
                'scope' => $goal->scope,
                'progress_percent' => $goal->progress_percent,
            ])->toArray(),
            'milestones' => JourneyMilestoneAchievement::where('specialist_id', $specialist->id)
                ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
                ->with('milestoneDefinition')
                ->orderBy('achieved_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($achievement) {
                    return [
                        'id' => $achievement->id,
                        'definition_code' => $achievement->milestoneDefinition->code,
                        'title' => $achievement->milestoneDefinition->title,
                        'achieved_at' => $achievement->achieved_at->format('Y-m-d'),
                        'tier' => $achievement->milestoneDefinition->tier,
                    ];
                })
                ->toArray(),
            'growth_opportunities' => $opportunities,
        ];
    }
}
