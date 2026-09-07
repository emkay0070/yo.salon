<?php

namespace App\Domain\Availability\Engines;

use App\Domain\Availability\Contracts\AvailabilityConstraint;
use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use Illuminate\Support\Collection;

/**
 * ConstraintPipeline
 *
 * Runs an ordered set of constraints against candidate windows.
 * Constraints are registered and composable — adding a new rule
 * (EquipmentConstraint, RoomConstraint, etc.) requires zero engine changes.
 */
class ConstraintPipeline
{
    /** @var AvailabilityConstraint[] */
    private array $constraints = [];

    public function register(AvailabilityConstraint $constraint): static
    {
        $this->constraints[] = $constraint;
        return $this;
    }

    /**
     * Run all registered constraints in sequence.
     *
     * @param  AvailabilityContext  $context
     * @param  Collection<WindowDTO>  $windows
     * @return Collection<WindowDTO>
     */
    public function run(AvailabilityContext $context, Collection $windows): Collection
    {
        foreach ($this->constraints as $constraint) {
            $windows = $constraint->apply($context, $windows);
        }

        return $windows;
    }
}
