<?php

namespace App\Domain\Availability\Constraints;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use App\Services\ResourceAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ResourceAvailabilityConstraint implements AvailabilityConstraint
{
    public function __construct(
        private ResourceAvailabilityService $resourceService
    ) {}

    public function apply(AvailabilityContext $context, Collection $windows): Collection
    {
        // If no service_id in context, pass through (no resource requirements to check)
        if (!isset($context->serviceId)) {
            return $windows;
        }

        return $windows->filter(function (WindowDTO $window) use ($context) {
            $start = CarbonImmutable::parse("{$context->date->format('Y-m-d')} {$window->startsAt}");
            $end = CarbonImmutable::parse("{$context->date->format('Y-m-d')} {$window->endsAt}");

            $result = $this->resourceService->isAvailableFor(
                serviceId: $context->serviceId,
                locationId: $context->locationId,
                start: $start,
                end: $end,
            );

            return $result->available;
        });
    }
}
