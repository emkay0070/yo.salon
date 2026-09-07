<?php

namespace App\Domain\Availability\Contracts;

use App\Domain\Availability\DTOs\AvailabilityContext;
use Illuminate\Support\Collection;

/**
 * AvailabilityConstraint
 *
 * The contract every constraint in the pipeline MUST implement.
 * - Input:  AvailabilityContext (immutable — no DB queries allowed)
 * - Input:  Collection of WindowDTO (the current candidate windows)
 * - Output: Collection of WindowDTO (filtered/modified)
 */
interface AvailabilityConstraint
{
    public function apply(AvailabilityContext $context, \Illuminate\Support\Collection $windows): \Illuminate\Support\Collection;
}
