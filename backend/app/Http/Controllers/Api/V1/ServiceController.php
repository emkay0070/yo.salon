<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // BelongsToSalon global scope automatically filters by auth user's salon
        $query = Service::with('salon')->withCount('bookings');
        $services = $query->get();
        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'duration' => 'required|integer',
            'category' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $validated;
        $data['salon_id'] = $salonId;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $data['image_path'] = $path;
        }

        $service = Service::create($data);
        return response()->json($service->load('salon'), 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json($service->load('salon'));
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|uuid|exists:salons,id',
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'duration' => 'sometimes|integer',
            'category' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $validated;
        
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $path = $request->file('image')->store('services', 'public');
            $data['image_path'] = $path;
        }

        $service->update($data);
        return response()->json($service->load('salon'));
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }
        $service->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        $services = Service::where('salon_id', $salon)
            ->where('active', true)
            ->get();
        return response()->json($services);
    }

    public function indexForPortal(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        if (!$salonId) {
            return response()->json(['message' => 'No salon context found'], 400);
        }

        $services = Service::withoutGlobalScope('salon')
            ->where('salon_id', $salonId)
            ->where('active', true)
            ->get();
            
        return response()->json($services);
    }
}
