<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $customer = auth()->guard('portal')->user();
        
        $query = Notification::query();

        // Filter by authenticated user type
        if ($user) {
            // Salon owner/staff
            $query->where('user_id', $user->id);
        } elseif ($customer) {
            // Customer portal
            $query->where('customer_id', $customer->id);
        }

        $query->orderBy('created_at', 'desc');

        $notifications = $query->paginate(20);

        // Calculate unread count based on user type
        $unreadQuery = Notification::query();
        if ($user) {
            $unreadQuery->where('user_id', $user->id);
        } elseif ($customer) {
            $unreadQuery->where('customer_id', $customer->id);
        }
        $unreadCount = $unreadQuery->whereNull('read_at')->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        
        $user = auth()->user();
        $customer = auth()->guard('portal')->user();
        
        // Check authorization based on user type
        $isAuthorized = false;
        if ($user && $notification->user_id === $user->id) {
            $isAuthorized = true;
        } elseif ($customer && $notification->customer_id === $customer->id) {
            $isAuthorized = true;
        }
        
        if (!$isAuthorized) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();
        $customer = auth()->guard('portal')->user();
        
        $query = Notification::query();
        
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($customer) {
            $query->where('customer_id', $customer->id);
        }
        
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
