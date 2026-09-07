<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerFavorite;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        $type = $request->query('type');
        $collectionId = $request->query('collection_id');
        
        $query = CustomerFavorite::query()->forCustomer($customerId)->with('favoritable');
        
        if ($type && $type !== 'all') {
            $query->byType($type);
        }
        
        if ($collectionId) {
            $query->byCollection($collectionId);
        }
        
        $favorites = $query->orderBy('added_at', 'desc')->paginate(20);
        
        return response()->json([
            'favorites' => $favorites,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:provider,specialist,service',
            'id' => 'required|uuid',
            'collection_id' => 'nullable|uuid',
        ]);
        
        $customerId = $request->attributes->get('customer_id');
        $salonId = $request->attributes->get('salon_id');
        
        // Map type to model class
        $typeMap = [
            'provider' => Provider::class,
            'specialist' => Specialist::class,
            'service' => Service::class,
        ];
        
        $favoritableType = $typeMap[$validated['type']];
        $favoritableId = $validated['id'];
        
        // Check if item exists
        $item = $favoritableType::findOrFail($favoritableId);
        
        // Check if already favorited
        $existing = CustomerFavorite::where('customer_id', $customerId)
            ->where('favoritable_type', $favoritableType)
            ->where('favoritable_id', $favoritableId)
            ->first();
        
        if ($existing) {
            return response()->json(['message' => 'Already favorited'], 409);
        }
        
        $favorite = CustomerFavorite::create([
            'customer_id' => $customerId,
            'salon_id' => $salonId,
            'favoritable_type' => $favoritableType,
            'favoritable_id' => $favoritableId,
            'collection_id' => $validated['collection_id'] ?? null,
            'added_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Added to favorites',
            'favorite' => $favorite->load('favoritable'),
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $favorite = CustomerFavorite::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        $favorite->delete();
        
        return response()->json(['message' => 'Removed from favorites']);
    }
}
