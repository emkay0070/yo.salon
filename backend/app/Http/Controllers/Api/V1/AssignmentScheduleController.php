<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSchedule;
use App\Models\SpecialistAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentScheduleController extends Controller
{
    public function index(string $salonId, string $specialistId): JsonResponse
    {
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('salon_id', $salonId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $schedules = AssignmentSchedule::where('assignment_id', $assignment->id)
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 8 END")
            ->get();

        $allActive = $schedules->every(fn($s) => ($s->status ?? 'ACTIVE') === 'ACTIVE');
        $hasAny = $schedules->count() > 0;

        return response()->json([
            'data' => $schedules,
            'schedule_mode' => $assignment->schedule_mode ?? 'INHERIT',
            'publish_status' => !$hasAny ? 'EMPTY' : ($allActive ? 'ACTIVE' : 'DRAFT'),
        ]);
    }

    public function update(Request $request, string $salonId, string $specialistId): JsonResponse
    {
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('salon_id', $salonId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $validated = $request->validate([
            'schedule_mode' => 'required|string|in:INHERIT,CUSTOM',
            'schedules' => 'nullable|array',
            'schedules.*.day_of_week' => 'required_with:schedules|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.open_time' => 'nullable|string',
            'schedules.*.close_time' => 'nullable|string',
            'schedules.*.is_closed' => 'required_with:schedules|boolean',
        ]);

        // Partial-valid save: incomplete rows land as DRAFT.
        $isComplete = true;
        if ($validated['schedule_mode'] === 'CUSTOM') {
            if (empty($validated['schedules'])) {
                $isComplete = false;
            } else {
                foreach ($validated['schedules'] as $s) {
                    if (empty($s['is_closed']) && (empty($s['open_time']) || empty($s['close_time']))) {
                        $isComplete = false;
                        break;
                    }
                }
            }
        }
        $status = $isComplete ? 'ACTIVE' : 'DRAFT';

        DB::beginTransaction();
        try {
            $assignment->update(['schedule_mode' => $validated['schedule_mode']]);

            if ($validated['schedule_mode'] === 'CUSTOM') {
                $upserts = $validated['schedules'] ?? [];
                $keptDays = [];

                foreach ($upserts as $scheduleData) {
                    $openTime = null;
                    if (!empty($scheduleData['open_time'])) {
                        $openTime = strlen($scheduleData['open_time']) === 5
                            ? $scheduleData['open_time'] . ':00'
                            : $scheduleData['open_time'];
                    }

                    $closeTime = null;
                    if (!empty($scheduleData['close_time'])) {
                        $closeTime = strlen($scheduleData['close_time']) === 5
                            ? $scheduleData['close_time'] . ':00'
                            : $scheduleData['close_time'];
                    }

                    $keptDays[] = $scheduleData['day_of_week'];
                    AssignmentSchedule::updateOrCreate(
                        [
                            'assignment_id' => $assignment->id,
                            'day_of_week' => $scheduleData['day_of_week'],
                        ],
                        [
                            'open_time' => $openTime,
                            'close_time' => $closeTime,
                            'is_closed' => (bool) $scheduleData['is_closed'],
                            'status'    => $status,
                            'published_at' => $status === 'ACTIVE' ? now() : null,
                            'published_by' => $status === 'ACTIVE' ? ($request->user()?->id ?? null) : null,
                            'updated_at' => now(),
                        ]
                    );
                }

                if (!empty($keptDays)) {
                    AssignmentSchedule::where('assignment_id', $assignment->id)
                        ->whereNotIn('day_of_week', $keptDays)
                        ->delete();
                }
            } else {
                AssignmentSchedule::where('assignment_id', $assignment->id)->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'ACTIVE'
                    ? 'Schedule updated and published.'
                    : 'Draft saved. Publish once the specialist hours are complete.',
                'publish_status' => $status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update assignment schedule: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule.',
            ], 500);
        }
    }

    public function publish(Request $request, string $salonId, string $specialistId): JsonResponse
    {
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('salon_id', $salonId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        if (($assignment->schedule_mode ?? 'INHERIT') !== 'CUSTOM') {
            return response()->json([
                'success' => false,
                'message' => 'This assignment inherits hours — nothing to publish.'
            ], 422);
        }

        $rows = AssignmentSchedule::where('assignment_id', $assignment->id)->get();
        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No schedule rows to publish. Add custom hours first.'
            ], 422);
        }

        foreach ($rows as $row) {
            if (empty($row->is_closed) && (empty($row->open_time) || empty($row->close_time))) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf('%s is missing open or close time. Cannot publish incomplete hours.', $row->day_of_week),
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            AssignmentSchedule::where('assignment_id', $assignment->id)->update([
                'status'       => 'ACTIVE',
                'published_at' => now(),
                'published_by' => $request->user()?->id ?? null,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish assignment schedules: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Publish failed.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Specialist hours published — now used for bookings.',
            'publish_status' => 'ACTIVE',
        ]);
    }
}
