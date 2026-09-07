<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $query = CustomerActivity::query()->forCustomer($customerId);
        
        // Filter by type if provided
        if ($request->has('type') && $request->type !== 'all') {
            $query->byType($request->type);
        }
        
        $activities = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return response()->json([
            'activities' => $activities,
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $activity = CustomerActivity::findOrFail($id);
        $customerId = $request->attributes->get('customer_id');
        
        if ($activity->customer_id !== $customerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $activity->markAsRead();
        
        return response()->json(['message' => 'Activity marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        CustomerActivity::forCustomer($customerId)
            ->unread()
            ->update(['read_at' => now()]);
        
        return response()->json(['message' => 'All activities marked as read']);
    }
}
