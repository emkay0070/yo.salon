<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Staff::with('salon');
        if ($request->has('salon_id')) {
            $query->where('salon_id', $request->query('salon_id'));
        }
        $staff = $query->get();
        return response()->json($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'specializations' => 'nullable',
            'availability' => 'nullable',
            'photo' => 'nullable|image|max:2048',
            'active' => 'sometimes|boolean',
            'role' => 'nullable|string',
        ]);

        $data = $validated;
        $data['salon_id'] = $salonId;

        // Set active to true by default if not provided
        if (!isset($data['active'])) {
            $data['active'] = true;
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('staff', 'public');
            $data['photo'] = $path;
        }

        // If specializations is a string, parse it as JSON (since frontend sends it as JSON string)
        if (isset($data['specializations']) && is_string($data['specializations'])) {
            $data['specializations'] = json_decode($data['specializations'], true);
        }

        // If availability is a string, parse it as JSON
        if (isset($data['availability']) && is_string($data['availability'])) {
            $data['availability'] = json_decode($data['availability'], true);
        }

        $staffMember = Staff::create($data);
        return response()->json($staffMember->load('salon'), 201);
    }

    public function show(Staff $staff): JsonResponse
    {
        return response()->json($staff->load('salon'));
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|uuid|exists:salons,id',
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'specializations' => 'nullable',
            'availability' => 'nullable',
            'photo' => 'nullable|image|max:2048',
            'active' => 'sometimes|boolean',
            'role' => 'nullable|string',
        ]);

        $data = $validated;
        
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($staff->photo) {
                Storage::disk('public')->delete($staff->photo);
            }
            $path = $request->file('photo')->store('staff', 'public');
            $data['photo'] = $path;
        }

        // If specializations is a string, parse it as JSON
        if (isset($data['specializations']) && is_string($data['specializations'])) {
            $data['specializations'] = json_decode($data['specializations'], true);
        }

        // If availability is a string, parse it as JSON
        if (isset($data['availability']) && is_string($data['availability'])) {
            $data['availability'] = json_decode($data['availability'], true);
        }

        $staff->update($data);
        return response()->json($staff->load('salon'));
    }

    public function destroy(Staff $staff): JsonResponse
    {
        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo);
        }
        $staff->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        $staff = Staff::where('salon_id', $salon)
            ->where('active', true)
            ->get();
        return response()->json($staff);
    }
}
