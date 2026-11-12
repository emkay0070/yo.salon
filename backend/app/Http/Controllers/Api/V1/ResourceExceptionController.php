<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResourceException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResourceExceptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exceptions = ResourceException::where('resource_id', $request->resource_id)->get();

        return response()->json($exceptions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => 'required|uuid|exists:resources,id',
            'date' => 'nullable|date',
            'day_of_week' => 'nullable|string|max:20',
            'exception_type' => 'required|string|max:50',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'reason' => 'nullable|string',
        ]);

        $exception = ResourceException::create($validated);

        return response()->json($exception, 201);
    }

    public function show(string $id): JsonResponse
    {
        $exception = ResourceException::findOrFail($id);

        return response()->json($exception);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $exception = ResourceException::findOrFail($id);

        $validated = $request->validate([
            'date' => 'nullable|date',
            'day_of_week' => 'nullable|string|max:20',
            'exception_type' => 'sometimes|string|max:50',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'reason' => 'nullable|string',
        ]);

        $exception->update($validated);

        return response()->json($exception);
    }

    public function destroy(string $id): JsonResponse
    {
        $exception = ResourceException::findOrFail($id);
        $exception->delete();

        return response()->json(null, 204);
    }
}
