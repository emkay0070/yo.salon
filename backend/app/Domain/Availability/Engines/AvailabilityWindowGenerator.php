<?php

namespace App\Domain\Availability\Engines;

use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\DTOs\WindowDTO;
use App\Domain\Availability\Constraints\ResolvedScheduleConstraint;
use App\Domain\Availability\Constraints\BookingConflictConstraint;
use App\Domain\Availability\Constraints\TimeOffConstraint;
use Illuminate\Support\Collection;

/**
 * AvailabilityWindowGenerator
 *
 * Generates raw availability windows from an AvailabilityContext
 * by running it through the ConstraintPipeline.
 *
 * This class is SEPARATE from AvailabilityEngine:
 * - WindowGenerator → builds and caches windows (runs via Queue Job)
 * - Engine          → reads cached windows + applies runtime constraints
 *
 * IMPORTANT: This class does NOT query the database. It receives
 * the AvailabilityContext (pre-fetched by AvailabilityContextBuilder)
 * and produces a Collection of WindowDTO.
 */
final class AvailabilityWindowGenerator
{
    private ConstraintPipeline $pipeline;

    public function __construct()
    {
        // Register the constraint pipeline in priority order.
        // Each constraint is pluggable — future constraints like
        // EquipmentConstraint or RoomConstraint just register here.
        $this->pipeline = (new ConstraintPipeline())
            ->register(new ResolvedScheduleConstraint())
            ->register(new ScheduleExceptionConstraint())
            ->register(new TimeOffConstraint())
            ->register(new BookingConflictConstraint());
    }

    /**
     * Generate availability windows for a given context.
     *
     * @param  AvailabilityContext  $context
     * @return Collection<WindowDTO>
     */
    public function generate(AvailabilityContext $context): Collection
    {
        // Start with a full-day window, then let constraints narrow it down.
        $initialWindow = collect([
            new WindowDTO(
                assignmentId: $context->assignmentId,
                date: $context->date,
                startsAt: '00:00:00',
                endsAt: '23:59:59',
                status: 'AVAILABLE',
            )
        ]);

        return $this->pipeline->run($context, $initialWindow);
    }
}
