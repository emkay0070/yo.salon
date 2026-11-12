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

        // Filter by authenticated user type and provider context
        if ($user) {
            // Salon owner/staff - enforce provider isolation
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if ($provider) {
                $query->where('provider_id', $provider->id);
            }
            $query->where('user_id', $user->id);
        } elseif ($customer) {
            // Customer portal - filter by customer only
            $query->where('customer_id', $customer->id);
        }

        $query->orderBy('created_at', 'desc');

        $notifications = $query->paginate(20);

        // Calculate unread count based on user type
        $unreadQuery = Notification::query();
        if ($user) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if ($provider) {
                $unreadQuery->where('provider_id', $provider->id);
            }
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
        
        // Check authorization based on user type and provider context
        $isAuthorized = false;
        if ($user) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if ($provider && $notification->provider_id === $provider->id && $notification->user_id === $user->id) {
                $isAuthorized = true;
            }
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
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if ($provider) {
                $query->where('provider_id', $provider->id);
            }
            $query->where('user_id', $user->id);
        } elseif ($customer) {
            $query->where('customer_id', $customer->id);
        }
        
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $preferences = $user->notification_preferences ?? [
            'emailBookings' => true,
            'smsReminders' => true,
            'pushUpdates' => false,
            'weeklyReports' => true,
        ];

        return response()->json(['preferences' => $preferences]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'emailBookings' => 'boolean',
            'smsReminders' => 'boolean',
            'pushUpdates' => 'boolean',
            'weeklyReports' => 'boolean',
        ]);

        $user->update(['notification_preferences' => $validated]);

        return response()->json(['message' => 'Preferences updated', 'preferences' => $validated]);
    }
}
