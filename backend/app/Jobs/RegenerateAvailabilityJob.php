<?php

namespace App\Jobs;

use App\Domain\Availability\Actions\RegenerateAvailabilityAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * RegenerateAvailabilityJob
 *
 * Async queue job that triggers the RegenerateAvailabilityAction.
 * 
 * Dispatched whenever any input that affects availability changes:
 * - Schedule changed, Leave added/removed, Booking created/cancelled, etc.
 */
class RegenerateAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $assignmentId,
        public readonly string $fromDate,
        public readonly string $toDate,
    ) {}

    public function handle(RegenerateAvailabilityAction $action): void
    {
        $action->execute($this->assignmentId, $this->fromDate, $this->toDate);
    }
}
