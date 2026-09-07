<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduleException;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ScheduleExceptionController
 *
 * Manages exceptions to the recurring weekly schedule at three hierarchy levels:
 *   provider   → applies to every branch of the signed-in owner's business
 *   salon      → applies to a specific branch
 *   assignment → applies to a specific specialist at a specific branch
 *
 * Example types: HOLIDAY, VACATION, TRAINING, CLOSURE, POWER_OUTAGE, EVENT, OTHER
 */
class ScheduleExceptionController extends Controller
{
    public function index(Request $request, string $salonId): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $scope = $request->query('scope', 'salon'); // 'provider' | 'salon' | 'assignment'
        $assignmentId = $request->query('assignment_id');

        if ($scope === 'provider') {
            $exceptions = ScheduleException::forProvider($salon->provider_id)
                ->orderByDesc('start_date')
                ->paginate(50);
        } elseif ($scope === 'assignment' && $assignmentId) {
            $exceptions = ScheduleException::forAssignment($assignmentId)
                ->where('scope_level', 'assignment')
                ->orderByDesc('start_date')
                ->paginate(50);
        } else {
            $exceptions = ScheduleException::forSalon($salonId)
                ->where('scope_level', 'salon')
                ->orderByDesc('start_date')
                ->paginate(50);
        }

        return response()->json(['data' => $exceptions->items(), 'meta' => [
            'total' => $exceptions->total(),
            'scope' => $scope,
        ]]);
    }

    public function store(Request $request, string $salonId): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'scope_level'   => 'required|string|in:provider,salon,assignment',
            'assignment_id' => 'nullable|string',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'title'         => 'required|string|max:150',
            'notes'         => 'nullable|string|max:1000',
            'type'          => 'required|string|in:HOLIDAY,VACATION,TRAINING,CLOSURE,POWER_OUTAGE,EVENT,OTHER',
            'is_closed'     => 'nullable|boolean',
            'open_time'     => 'nullable|string',
            'close_time'    => 'nullable|string',
            'break_start'   => 'nullable|string',
            'break_end'     => 'nullable|string',
            'recurring_yearly' => 'nullable|boolean',
        ]);

        if ($validated['scope_level'] === 'assignment' && empty($validated['assignment_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'assignment_id is required when scope_level = assignment.',
            ], 422);
        }

        $isClosed = $validated['is_closed'] ?? true;
        if (!$isClosed && (empty($validated['open_time']) || empty($validated['close_time']))) {
            return response()->json([
                'success' => false,
                'message' => 'Partial-day exceptions must include open_time and close_time.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $normalizedOpen = null;
            if (!empty($validated['open_time'])) {
                $normalizedOpen = strlen($validated['open_time']) === 5
                    ? $validated['open_time'] . ':00'
                    : $validated['open_time'];
            }
            $normalizedClose = null;
            if (!empty($validated['close_time'])) {
                $normalizedClose = strlen($validated['close_time']) === 5
                    ? $validated['close_time'] . ':00'
                    : $validated['close_time'];
            }
            $normalizedBreakStart = null;
            if (!empty($validated['break_start'])) {
                $normalizedBreakStart = strlen($validated['break_start']) === 5
                    ? $validated['break_start'] . ':00'
                    : $validated['break_start'];
            }
            $normalizedBreakEnd = null;
            if (!empty($validated['break_end'])) {
                $normalizedBreakEnd = strlen($validated['break_end']) === 5
                    ? $validated['break_end'] . ':00'
                    : $validated['break_end'];
            }

            $exception = ScheduleException::create([
                'scope_level'       => $validated['scope_level'],
                'provider_id'       => $salon->provider_id,
                'salon_id'          => $validated['scope_level'] === 'salon' ? $salonId : null,
                'assignment_id'     => $validated['scope_level'] === 'assignment' ? ($validated['assignment_id'] ?? null) : null,
                'start_date'        => $validated['start_date'],
                'end_date'          => $validated['end_date'] ?? null,
                'title'             => $validated['title'],
                'notes'             => $validated['notes'] ?? null,
                'type'              => $validated['type'],
                'is_closed'         => $isClosed,
                'open_time'         => $normalizedOpen,
                'close_time'        => $normalizedClose,
                'break_start'       => $normalizedBreakStart,
                'break_end'         => $normalizedBreakEnd,
                'recurring_yearly'  => $validated['recurring_yearly'] ?? false,
                'created_by'        => auth()->id(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Exception added.',
                'data'    => $exception,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ScheduleException store failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create exception.',
            ], 500);
        }
    }

    public function update(Request $request, string $salonId, string $id): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exception = ScheduleException::find($id);
        if (!$exception) {
            return response()->json(['message' => 'Exception not found'], 404);
        }

        $allowedToEdit =
            $exception->provider_id === $salon->provider_id &&
            (
                $exception->scope_level === 'provider' ||
                $exception->salon_id === $salonId ||
                ($exception->assignment_id && $exception->assignment?->salon_id === $salonId)
            );

        if (!$allowedToEdit) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date'   => 'sometimes|date',
            'end_date'     => 'sometimes|nullable|date|after_or_equal:start_date',
            'title'        => 'sometimes|string|max:150',
            'notes'        => 'sometimes|nullable|string|max:1000',
            'type'         => 'sometimes|string|in:HOLIDAY,VACATION,TRAINING,CLOSURE,POWER_OUTAGE,EVENT,OTHER',
            'is_closed'    => 'sometimes|boolean',
            'open_time'    => 'sometimes|nullable|string',
            'close_time'   => 'sometimes|nullable|string',
            'break_start'  => 'sometimes|nullable|string',
            'break_end'    => 'sometimes|nullable|string',
            'recurring_yearly' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            $patch = [];
            foreach ($validated as $k => $v) {
                if (in_array($k, ['open_time', 'close_time', 'break_start', 'break_end']) && is_string($v)) {
                    $patch[$k] = strlen($v) === 5 ? $v . ':00' : $v;
                } else {
                    $patch[$k] = $v;
                }
            }
            $exception->update($patch);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exception updated.',
                'data' => $exception,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ScheduleException update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exception.',
            ], 500);
        }
    }

    public function destroy(Request $request, string $salonId, string $id): JsonResponse
    {
        $salon = $request->attributes->get('salon');
        if (!$salon || $salon->id !== $salonId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exception = ScheduleException::find($id);
        if (!$exception) {
            return response()->json(['message' => 'Exception not found'], 404);
        }

        $allowedToEdit =
            $exception->provider_id === $salon->provider_id &&
            (
                $exception->scope_level === 'provider' ||
                $exception->salon_id === $salonId ||
                ($exception->assignment_id && optional($exception->assignment)->salon_id === $salonId)
            );

        if (!$allowedToEdit) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exception->delete();

        return response()->json([
            'success' => true,
            'message' => 'Exception deleted.',
        ]);
    }
}
