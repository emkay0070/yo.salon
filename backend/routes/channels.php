<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user has access to the channel.
|
*/

// Salon channel - only accessible to users belonging to that salon
Broadcast::channel('salon.{id}', function ($user, $salonId) {
    return $user->currentSalon()?->id == (int) $salonId;
});

// Customer channel - only accessible to that specific customer
Broadcast::channel('customer.{id}', function ($user, $customerId) {
    // For now, we'll allow if the user is the customer
    // In the future, this should check against the customer portal auth guard
    return $user->id == (int) $customerId;
});

// Staff channel - only accessible to staff members of that salon
Broadcast::channel('staff.{id}', function ($user, $staffId) {
    // Check if the user is the staff member
    return $user->id == (int) $staffId;
});

// Presence channel for salon dashboard (shows who's online)
Broadcast::channel('presence.salon.{id}', function ($user, $salonId) {
    if ($user->currentSalon()?->id != (int) $salonId) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});
