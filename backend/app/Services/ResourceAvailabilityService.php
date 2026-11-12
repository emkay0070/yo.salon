<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\ResourceRequirement;
use App\Domain\Availability\DTOs\ResourceAvailabilityResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ResourceAvailabilityService
{
    public function isAvailableFor(
        string $serviceId,
        ?string $locationId,   // physical location (null = mobile/independent)
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): ResourceAvailabilityResult {
        // 1. Get service resource requirements
        $requirements = ResourceRequirement::where('service_id', $serviceId)->get();

        if ($requirements->isEmpty()) {
            return new ResourceAvailabilityResult(
                available: true,
                candidates: collect(),
            );
        }

        // 2. For each requirement group, find eligible and available resources
        $candidates = collect();
        foreach ($requirements as $requirement) {
            $eligible  = $this->findEligibleResources($requirement, $locationId);
            $available = $this->filterAvailableResources($eligible, $start, $end);
            
            // Check if we have enough resources for count
            if ($available->count() < $requirement->count) {
                return new ResourceAvailabilityResult(
                    available: false,
                    candidates: collect(),
                    reason: "Insufficient resources for requirement: {$requirement->resource_type}",
                );
            }

            $candidates->put($requirement->id, $available);
        }

        return new ResourceAvailabilityResult(
            available: true,
            candidates: $candidates,
        );
    }

    private function findEligibleResources(ResourceRequirement $requirement, ?string $locationId): Collection
    {
        // Resources belong to a physical Location.
        // If no locationId (mobile provider), no physical resources to check.
        if (!$locationId) {
            return collect();
        }

        $query = Resource::where('location_id', $locationId)
            ->where('type', $requirement->resource_type)
            ->where('is_active', true)
            ->where('capacity', '>=', $requirement->min_capacity ?? 1);

        // Capability filter: ALL required capabilities must be present
        $requiredCapabilities = $requirement->required_capabilities;
        if (!empty($requiredCapabilities)) {
            $query->whereHas('capabilities', function ($capabilityQuery) use ($requiredCapabilities) {
                $capabilityQuery->whereIn('capability', $requiredCapabilities)
                    ->groupBy('resource_id')
                    ->havingRaw('COUNT(DISTINCT capability) = ?', [count($requiredCapabilities)]);
            });
        }

        return $query->get();
    }

    private function filterAvailableResources(Collection $resources, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $resources->filter(function ($resource) use ($start, $end) {
            // Check resource exceptions
            if ($this->hasException($resource, $start, $end)) {
                return false;
            }

            // Check existing allocations
            if ($this->hasAllocation($resource, $start, $end)) {
                return false;
            }

            return true;
        });
    }

    private function hasException(Resource $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        // Check if resource has exception during this interval
        return $resource->exceptions()
            ->where(function ($query) use ($start, $end) {
                // One-time exception
                $query->whereDate('date', $start->toDateString())
                    ->where(function ($q) use ($start, $end) {
                        $q->where(function ($q1) use ($end) {
                            $q1->whereNull('start_time')
                                ->orWhere('start_time', '<=', $end->format('H:i:s'));
                        })->where(function ($q2) use ($start) {
                            $q2->whereNull('end_time')
                                ->orWhere('end_time', '>=', $start->format('H:i:s'));
                        });
                    });
            })
            ->orWhere(function ($query) use ($start, $end) {
                // Recurring exception
                $query->where('day_of_week', $start->format('l'))
                    ->where(function ($q) use ($start, $end) {
                        $q->where(function ($q1) use ($end) {
                            $q1->whereNull('start_time')
                                ->orWhere('start_time', '<=', $end->format('H:i:s'));
                        })->where(function ($q2) use ($start) {
                            $q2->whereNull('end_time')
                                ->orWhere('end_time', '>=', $start->format('H:i:s'));
                        });
                    });
            })
            ->exists();
    }

    private function hasAllocation(Resource $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        // Check if resource has existing allocation during this interval
        // Apply turnaround_time buffer
        $bufferStart = $start->subMinutes($resource->turnaround_time);
        $bufferEnd = $end->addMinutes($resource->turnaround_time);

        return $resource->allocations()
            ->where(function ($query) use ($bufferStart, $bufferEnd) {
                $query->where('start_time', '<', $bufferEnd)
                    ->where('end_time', '>', $bufferStart);
            })
            ->exists();
    }
}
