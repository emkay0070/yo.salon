<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();
        if ($request->has('salon_id')) {
            $salonId = $request->query('salon_id');
            $query->whereHas('bookings', function($q) use ($salonId) {
                $q->where('salon_id', $salonId);
            });
        }
        $customers = $query->with('bookings')->get();
        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|required_without:auth|uuid|exists:salons,id',
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
            'visits' => 'sometimes|integer',
        ]);

        // If user is authenticated, salon_id is auto-set by BelongsToSalon trait
        // If no auth (guest booking), use salon_id from request
        if (auth()->check()) {
            $salonId = request()->attributes->get('salon_id');
            unset($validated['salon_id']); // Remove so trait can set it
        } else {
            $salonId = $validated['salon_id'];
        }

        // Check if customer already exists by phone or email within the salon
        $existingCustomer = Customer::findByContact($validated['phone'], $salonId);
        
        if ($existingCustomer) {
            return response()->json([
                'message' => 'Customer already exists',
                'customer' => $existingCustomer,
            ], 200);
        }

        // For guest booking, use withoutSalonScope to bypass auto-set
        if (!auth()->check()) {
            $customer = Customer::withoutSalonScope()->create($validated);
        } else {
            $customer = Customer::create($validated);
        }
        
        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load('bookings'));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
            'visits' => 'sometimes|integer',
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(null, 204);
    }
}
