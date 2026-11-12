<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the notification.
     */
    public function view(mixed $user, Notification $notification): bool
    {
        // User can view if it belongs to them and their provider
        if ($user instanceof User && $notification->user_id === $user->id) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if ($provider && $notification->provider_id === $provider->id) {
                return true;
            }
        }

        // Customer can view if it belongs to them
        if ($user instanceof Customer && $notification->customer_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update the notification (mark as read).
     */
    public function update(mixed $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }

    /**
     * Determine if the user can delete the notification.
     */
    public function delete(mixed $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }

    /**
     * Scope: Only return notifications belonging to the user's provider.
     * Used for provider-scoped queries.
     */
    public function scopeForProvider(mixed $user, $query)
    {
        if ($user instanceof User && method_exists($user, 'currentProvider')) {
            $provider = $user->currentProvider();
            if ($provider) {
                return $query->where('provider_id', $provider->id);
            }
        }

        // For customers, return only their notifications
        if ($user instanceof Customer) {
            return $query->where('customer_id', $user->id);
        }

        return $query;
    }
}
