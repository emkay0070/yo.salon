<?php

namespace App\Domain\Availability\Actions;

use App\Domain\Availability\Engines\AvailabilityWindowGenerator;
use App\Domain\Availability\Queries\AvailabilityContextBuilder;
use App\Jobs\RegenerateAvailabilityJob;
use App\Models\AvailabilityWindow;
use App\Models\SpecialistAssignment;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RegenerateAvailabilityAction
 *
 * Dispatches the async job that regenerates availability_windows for an assignment.
 * This action should be called whenever any input that affects availability changes:
 * 
 * - Assignment schedule changed
 * - Salon schedule changed
 * - Provider schedule changed
 * - Time-off added or removed
 * - Booking created or cancelled
 */
class RegenerateAvailabilityAction
{
    /**
     * Dispatch the regeneration job for a date range.
     * Defaults to the next 30 days if no range provided.
     */
    public function dispatch(
        string $assignmentId,
        CarbonImmutable $from = null,
        CarbonImmutable $to = null
    ): void {
        $from ??= CarbonImmutable::today();
        $to   ??= $from->addDays(30);

        RegenerateAvailabilityJob::dispatch($assignmentId, $from->toDateString(), $to->toDateString());
    }

    /**
     * Execute synchronously — only call this within the Queue Job itself.
     */
    public function execute(string $assignmentId, string $fromDate, string $toDate): void
    {
        $query     = app(AvailabilityContextBuilder::class);
        $generator = app(AvailabilityWindowGenerator::class);
        $assignment = SpecialistAssignment::findOrFail($assignmentId);

        $current = CarbonImmutable::parse($fromDate);
        $end     = CarbonImmutable::parse($toDate);

        // Increment version to invalidate old cached windows
        $newVersion = DB::table('availability_windows')
            ->where('assignment_id', $assignmentId)
            ->max('version') + 1;

        while ($current <= $end) {
            $context = $query->build($assignmentId, $current);
            $windows = $generator->generate($context);

            // Delete stale windows for this date
            AvailabilityWindow::where('assignment_id', $assignmentId)
                ->where('date', $current->toDateString())
                ->delete();

            // Insert new windows
            foreach ($windows as $windowDTO) {
                AvailabilityWindow::create([
                    'id'            => (string) Str::uuid(),
                    'assignment_id' => $assignmentId,
                    'date'          => $current->toDateString(),
                    'starts_at'     => $windowDTO->startsAt,
                    'ends_at'       => $windowDTO->endsAt,
                    'status'        => $windowDTO->status,
                    'version'       => $newVersion,
                ]);
            }

            $current = $current->addDay();
        }
    }
}
