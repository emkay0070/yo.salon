<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalonSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalonScheduleController extends Controller
{
    public function index($salonId): JsonResponse
    {
        $salon = request()->attributes->get('salon');

        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $schedules = SalonSchedule::where('salon_id', $salonId)
            ->orderByRaw("CASE day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
                WHEN 'Sunday' THEN 7
                ELSE 8
            END")
            ->get();
        $allActive = $schedules->every(fn($s) => ($s->status ?? 'ACTIVE') === 'ACTIVE');
        $hasAny = $schedules->count() > 0;

        return response()->json([
            'data' => $schedules,
            'schedule_mode' => $salon->schedule_mode ?? 'INHERIT',
            'publish_status' => !$hasAny ? 'EMPTY' : ($allActive ? 'ACTIVE' : 'DRAFT'),
        ]);
    }

    public function update(Request $request, $salonId): JsonResponse
    {
        $salon = $request->attributes->get('salon');

        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'schedule_mode' => 'required|string|in:INHERIT,CUSTOM',
            'schedules' => 'nullable|array',
            'schedules.*.day_of_week' => 'required_with:schedules|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.open_time' => 'nullable|string',
            'schedules.*.close_time' => 'nullable|string',
            'schedules.*.is_closed' => 'required_with:schedules|boolean',
        ]);

        // Instead of rejecting partial saves, we let them land and mark status = DRAFT.
        // The owner clicks Publish later once everything is correct.
        $isComplete = true;
        if ($validated['schedule_mode'] === 'CUSTOM') {
            if (empty($validated['schedules'])) {
                $isComplete = false;
            } else {
                foreach ($validated['schedules'] as $s) {
                    if (empty($s['is_closed'])) {
                        if (empty($s['open_time']) || empty($s['close_time'])) {
                            $isComplete = false;
                            break;
                        }
                    }
                }
            }
        }
        $status = $isComplete ? 'ACTIVE' : 'DRAFT';

        DB::beginTransaction();
        try {
            $salon->update([
                'schedule_mode' => $validated['schedule_mode']
            ]);

            if ($validated['schedule_mode'] === 'CUSTOM') {
                $upserts = $validated['schedules'] ?? [];
                $keptDays = [];

                foreach ($upserts as $scheduleData) {
                    $openTime = null;
                    if (!empty($scheduleData['open_time'])) {
                        $openTime = strlen($scheduleData['open_time']) === 5 ? $scheduleData['open_time'] . ':00' : $scheduleData['open_time'];
                    }

                    $closeTime = null;
                    if (!empty($scheduleData['close_time'])) {
                        $closeTime = strlen($scheduleData['close_time']) === 5 ? $scheduleData['close_time'] . ':00' : $scheduleData['close_time'];
                    }

                    $keptDays[] = $scheduleData['day_of_week'];
                    SalonSchedule::updateOrCreate(
                        [
                            'salon_id' => $salonId,
                            'day_of_week' => $scheduleData['day_of_week']
                        ],
                        [
                            'open_time' => $openTime,
                            'close_time' => $closeTime,
                            'is_closed' => (bool)$scheduleData['is_closed'],
                            'status'    => $status,
                            'published_at' => $status === 'ACTIVE' ? now() : null,
                            'created_by' => auth()->id(),
                            'updated_at' => now(),
                        ]
                    );
                }

                // Drop days no longer present, rather than leaving stale rows.
                if (!empty($keptDays)) {
                    SalonSchedule::where('salon_id', $salonId)
                        ->whereNotIn('day_of_week', $keptDays)
                        ->delete();
                }
            } else {
                // INHERIT mode — drop the custom rows for this salon entirely.
                SalonSchedule::where('salon_id', $salonId)->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'ACTIVE'
                    ? 'Operating hours updated and published.'
                    : 'Draft saved. Review and publish to apply these hours to bookings.',
                'publish_status' => $status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update salon schedules: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update operating hours.'
            ], 500);
        }
    }

    /**
     * Publish draft hours (switch all salon schedule rows to ACTIVE).
     */
    public function publish(Request $request, $salonId): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (($salon->schedule_mode ?? 'INHERIT') !== 'CUSTOM') {
            return response()->json([
                'success' => false,
                'message' => 'This branch inherits hours from the provider — nothing to publish.'
            ], 422);
        }

        $rows = SalonSchedule::where('salon_id', $salonId)->get();
        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No operating hours to publish. Add custom hours first.'
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
            SalonSchedule::where('salon_id', $salonId)->update([
                'status'       => 'ACTIVE',
                'published_at' => now(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish salon schedules: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Publish failed.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Operating hours published — bookings now use these hours.',
            'publish_status' => 'ACTIVE',
        ]);
    }
}

