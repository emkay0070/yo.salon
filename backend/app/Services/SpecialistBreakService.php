<?php

namespace App\Services;

use App\Models\Specialist;
use App\Models\SpecialistBreak;
use App\Models\BookingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SpecialistBreakService
{
    /**
     * Start a break for a specialist.
     */
    public function startBreak(Specialist $specialist, $actor, int $durationMinutes, string $providerId, string $locationId = null): SpecialistBreak
    {
        $this->authorizeActorForFloor($actor, $providerId, $specialist, $locationId);

        // Rule 1: Cannot start a break while serving an active session
        $activeSessionExists = BookingService::whereHas('booking', function ($q) use ($specialist) {
                $q->where('specialist_id', $specialist->id);
            })
            ->where('session_status', 'in_progress')
            ->exists();
            
        if ($activeSessionExists) {
            throw ValidationException::withMessages([
                'break' => 'Cannot start a break while serving an active session.'
            ]);
        }

        // Rule 2: Cannot start a second break while one is operationally active
        $activeBreakExists = SpecialistBreak::where('specialist_id', $specialist->id)
            ->whereNull('actual_end_at')
            ->where('expected_end_at', '>', now())
            ->exists();
            
        if ($activeBreakExists) {
            throw ValidationException::withMessages([
                'break' => 'Specialist is already on an active break.'
            ]);
        }

        // Rule 3: Record break
        $now = now();
        
        $break = SpecialistBreak::create([
            'specialist_id' => $specialist->id,
            'started_by_actor_type' => get_class($actor),
            'started_by_actor_id' => $actor->id,
            'started_at' => $now,
            'expected_end_at' => $now->copy()->addMinutes($durationMinutes),
        ]);

        return $break;
    }

    /**
     * End the currently active break for a specialist.
     */
    public function endBreak(Specialist $specialist, $actor, string $providerId, string $locationId = null): SpecialistBreak
    {
        $this->authorizeActorForFloor($actor, $providerId, $specialist, $locationId);

        $activeBreak = SpecialistBreak::where('specialist_id', $specialist->id)
            ->whereNull('actual_end_at')
            ->latest('started_at')
            ->first();
            
        if (!$activeBreak) {
            throw ValidationException::withMessages([
                'break' => 'Specialist does not have an open break to end.'
            ]);
        }

        $activeBreak->update([
            'actual_end_at' => now(),
            'ended_by_actor_type' => get_class($actor),
            'ended_by_actor_id' => $actor->id,
        ]);

        return $activeBreak;
    }

    /**
     * Authorize the actor (Specialist or Staff User) for this floor operation.
     */
    private function authorizeActorForFloor($actor, string $providerId, Specialist $specialist, ?string $locationId = null)
    {
        if ($actor instanceof \App\Models\SpecialistAccount) {
            if ($actor->specialist_id !== $specialist->id) {
                throw ValidationException::withMessages([
                    'authorization' => 'You can only manage your own breaks.'
                ]);
            }
            return;
        }

        if ($actor instanceof \App\Models\User) {
            $providerUser = \App\Models\ProviderUser::where('user_id', $actor->id)
                ->where('provider_id', $providerId)
                ->first();

            if (!$providerUser) {
                throw ValidationException::withMessages([
                    'authorization' => 'You do not belong to this provider.'
                ]);
            }

            $authorityService = app(\App\Services\ProviderUserAuthorityService::class);
            if (!$authorityService->canPerformAction($providerUser, 'operate_floor')) {
                throw ValidationException::withMessages([
                    'authorization' => 'You do not have permission to operate the floor.'
                ]);
            }

            if ($locationId && !$authorityService->canAccessLocation($providerUser, $locationId)) {
                throw ValidationException::withMessages([
                    'authorization' => 'You do not have permission to access this location.'
                ]);
            }
            return;
        }

        throw ValidationException::withMessages([
            'authorization' => 'Unknown actor type.'
        ]);
    }
}
