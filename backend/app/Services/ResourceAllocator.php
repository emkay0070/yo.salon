<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\ResourceRequirement;
use App\Models\Booking;
use App\Models\ResourceAllocation as ResourceAllocationModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Exception;

class ResourceAllocator
{
    private BookingConflictService $conflictService;

    public function __construct(BookingConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
    }

    public function allocateResources(
        string $serviceId,
        ?string $locationId,   // physical location (null = mobile provider)
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
    ): Collection {
        // 1. Get service resource requirements
        $requirements = ResourceRequirement::where('service_id', $serviceId)->get();

        // 2. For each requirement, select available resources
        $allocations = collect();
        foreach ($requirements as $requirement) {
            $resources = $this->selectResources($requirement, $locationId, $date, $startTime, $endTime);
            
            if ($resources->count() < $requirement->count) {
                throw new Exception("Insufficient resources for requirement: {$requirement->resource_type}");
            }

            // Select first N resources (deterministic by id ASC)
            $selected = $resources->sortBy('id')->take($requirement->count);
            
            foreach ($selected as $resource) {
                $allocations->push([
                    'requirement_id' => $requirement->id,
                    'resource_id' => $resource->id,
                ]);
            }
        }

        return $allocations;
    }

    private function selectResources(
        ResourceRequirement $requirement,
        ?string $locationId,
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
    ): Collection {
        $candidates = $this->findEligibleResources($requirement, $locationId);

        $available = $candidates->filter(function ($resource) use ($date, $startTime, $endTime) {
            return $this->isResourceAvailable($resource, $date, $startTime, $endTime);
        });

        return $available;
    }

    private function findEligibleResources(ResourceRequirement $requirement, ?string $locationId): Collection
    {
        // Resources belong to a physical Location in the destination architecture.
        // If no locationId (mobile provider), skip physical resource allocation.
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

    private function isResourceAvailable(
        Resource $resource,
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
    ): bool {
        $start = CarbonImmutable::parse("{$date->format('Y-m-d')} {$startTime}");
        $end = CarbonImmutable::parse("{$date->format('Y-m-d')} {$endTime}");

        // Check resource exceptions
        if ($this->hasException($resource, $start, $end)) {
            return false;
        }

        // Check existing allocations
        if ($this->hasAllocation($resource, $start, $end)) {
            return false;
        }

        return true;
    }

    private function hasException(Resource $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $resource->exceptions()
            ->where(function ($query) use ($start, $end) {
                $query->where('date', $start->toDateString())
                    ->where(function ($q) use ($start, $end) {
                        $q->whereNull('start_time')
                            ->orWhere('start_time', '<=', $end->format('H:i:s'))
                            ->where(function ($q2) use ($start) {
                                $q2->whereNull('end_time')
                                    ->orWhere('end_time', '>=', $start->format('H:i:s'));
                            });
                    });
            })
            ->orWhere(function ($query) use ($start, $end) {
                $query->where('day_of_week', $start->format('l'))
                    ->where(function ($q) use ($start, $end) {
                        $q->whereNull('start_time')
                            ->orWhere('start_time', '<=', $end->format('H:i:s'))
                            ->where(function ($q2) use ($start) {
                                $q2->whereNull('end_time')
                                    ->orWhere('end_time', '>=', $start->format('H:i:s'));
                            });
                    });
            })
            ->exists();
    }

    private function hasAllocation(Resource $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $bufferStart = $start->subMinutes($resource->turnaround_time);
        $bufferEnd = $end->addMinutes($resource->turnaround_time);

        return $resource->allocations()
            ->whereHas('booking', function ($query) {
                // Lifecycle-aware status check
                $query->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
                      ->where(function ($q) {
                          $q->where('status', '!=', 'reserved')
                            ->orWhereNull('slot_locked_until')
                            ->orWhere('slot_locked_until', '>', now());
                      });
            })
            ->where(function ($query) use ($bufferStart, $bufferEnd) {
                $query->where('start_time', '<', $bufferEnd)
                    ->where('end_time', '>', $bufferStart);
            })
            ->exists();
    }
}
