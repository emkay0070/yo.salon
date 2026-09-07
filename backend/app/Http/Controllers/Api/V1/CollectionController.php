<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $collections = CustomerCollection::forCustomer($customerId)
            ->withCount('favorites')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'collections' => $collections,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);
        
        $customerId = $request->attributes->get('customer_id');
        $salonId = $request->attributes->get('salon_id');
        
        $collection = CustomerCollection::create([
            'customer_id' => $customerId,
            'salon_id' => $salonId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_default' => false,
        ]);
        
        return response()->json([
            'message' => 'Collection created',
            'collection' => $collection,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $collection = CustomerCollection::where('id', $id)
            ->where('customer_id', $customerId)
            ->with(['favorites.favoritable'])
            ->firstOrFail();
        
        return response()->json([
            'collection' => $collection,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);
        
        $customerId = $request->attributes->get('customer_id');
        
        $collection = CustomerCollection::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        $collection->update($validated);
        
        return response()->json([
            'message' => 'Collection updated',
            'collection' => $collection,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $collection = CustomerCollection::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        $collection->delete();
        
        return response()->json(['message' => 'Collection deleted']);
    }
}
