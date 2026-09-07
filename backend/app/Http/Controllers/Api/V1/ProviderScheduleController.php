<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProviderSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProviderScheduleController extends Controller
{
    public function index($providerId): JsonResponse
    {
        $salon = request()->attributes->get('salon');

        if (!$salon || $salon->provider_id !== $providerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $schedules = ProviderSchedule::where('provider_id', $providerId)
            ->orderBy('day_of_week')
            ->get();

        // Convert integer day_of_week to day names for frontend
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $formattedSchedules = $schedules->map(function($schedule) use ($dayNames) {
            return [
                'id' => $schedule->id,
                'provider_id' => $schedule->provider_id,
                'day_of_week' => $dayNames[$schedule->day_of_week] ?? 'Unknown',
                'open_time' => $schedule->open_time,
                'close_time' => $schedule->close_time,
                'is_closed' => $schedule->is_closed,
                'status' => $schedule->status,
                'published_at' => $schedule->published_at,
            ];
        });

        $allActive = $schedules->every(fn($s) => ($s->status ?? 'ACTIVE') === 'ACTIVE');
        $hasAny = $schedules->count() > 0;

        return response()->json([
            'data' => $formattedSchedules,
            'publish_status' => !$hasAny ? 'EMPTY' : ($allActive ? 'ACTIVE' : 'DRAFT'),
        ]);
    }

    public function update(Request $request, $providerId): JsonResponse
    {
        $salon = $request->attributes->get('salon');

        if (!$salon || $salon->provider_id !== $providerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.open_time' => 'nullable|string',
            'schedules.*.close_time' => 'nullable|string',
            'schedules.*.is_closed' => 'required|boolean',
        ]);

        // Convert day names to integers
        $dayMap = [
            'Sunday' => 0,
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
        ];

        // Determine if schedules are complete
        $isComplete = true;
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
        $status = $isComplete ? 'ACTIVE' : 'DRAFT';

        DB::beginTransaction();
        try {
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

                $dayInteger = $dayMap[$scheduleData['day_of_week']] ?? 0;
                $keptDays[] = $dayInteger;
                ProviderSchedule::updateOrCreate(
                    [
                        'provider_id' => $providerId,
                        'day_of_week' => $dayInteger
                    ],
                    [
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                        'is_closed' => (bool)$scheduleData['is_closed'],
                        'status'    => $status,
                        'published_at' => $status === 'ACTIVE' ? now() : null,
                        'updated_at' => now(),
                    ]
                );
            }

            // Drop days no longer present
            if (!empty($keptDays)) {
                ProviderSchedule::where('provider_id', $providerId)
                    ->whereNotIn('day_of_week', $keptDays)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'ACTIVE'
                    ? 'Business hours updated and published.'
                    : 'Draft saved. Review and publish to apply these hours to bookings.',
                'publish_status' => $status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update provider schedules: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update business hours.'
            ], 500);
        }
    }

    public function publish(Request $request, $providerId): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->provider_id !== $providerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rows = ProviderSchedule::where('provider_id', $providerId)->get();
        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No business hours to publish. Add hours first.'
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
            ProviderSchedule::where('provider_id', $providerId)->update([
                'status'       => 'ACTIVE',
                'published_at' => now(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish provider schedules: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Publish failed.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Business hours published — bookings now use these hours.',
            'publish_status' => 'ACTIVE',
        ]);
    }
}
