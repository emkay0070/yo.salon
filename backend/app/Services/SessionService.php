<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Validation\ValidationException;

class SessionService
{
    /**
     * Start a session for a booking service.
     *
     * @param BookingService $bookingService
     * @param mixed $actor
     * @return BookingService
     * @throws ValidationException
     */
    public function startSession(BookingService $bookingService, $actor): BookingService
    {
        $booking = Booking::find($bookingService->booking_id);

        // Validate booking is in a valid state
        if (!in_array($booking->status, ['confirmed', 'arrived', 'waiting'])) {
            throw ValidationException::withMessages([
                'booking' => "Booking must be confirmed, arrived, or waiting before starting a session. Current status: {$booking->status}"
            ]);
        }

        // Validate specialist owns this booking
        if ($booking->specialist_id === null) {
            throw ValidationException::withMessages([
                'specialist' => 'Booking has no assigned specialist.'
            ]);
        }

        $specialist = Specialist::findOrFail($booking->specialist_id);

        // Authorize actor
        $this->authorizeActorForSession($actor, $booking, $specialist);

        // Validate specialist has active assignment at the booking's provider/location
        $assignment = SpecialistAssignment::where('specialist_id', $specialist->id)
            ->where('provider_id', $booking->provider_id)
            ->whereIn('status', ['ACTIVE', 'active'])
            ->first();

        if (!$assignment) {
            throw ValidationException::withMessages([
                'specialist' => 'The assigned specialist does not have an active assignment at this provider.'
            ]);
        }

        // Concurrency check: specialist cannot have another active session
        $activeSessionExists = BookingService::whereHas('booking', function ($q) use ($specialist) {
                $q->where('specialist_id', $specialist->id);
            })
            ->where('session_status', 'in_progress')
            ->exists();
            
        if ($activeSessionExists) {
            throw ValidationException::withMessages([
                'session' => 'Specialist is already serving another customer.'
            ]);
        }

        // Break check: specialist cannot be on an active break
        $activeBreakExists = \App\Models\SpecialistBreak::where('specialist_id', $specialist->id)
            ->whereNull('actual_end_at')
            ->where('expected_end_at', '>', now())
            ->exists();

        if ($activeBreakExists) {
            throw ValidationException::withMessages([
                'session' => 'Cannot start session: Specialist is currently on break.'
            ]);
        }

        // Validate session is in scheduled state
        if ($bookingService->session_status !== 'scheduled') {
            throw ValidationException::withMessages([
                'session' => "Session is not in scheduled state. Current status: {$bookingService->session_status}"
            ]);
        }

        // Validate session has not already started
        if ($bookingService->started_at !== null) {
            throw ValidationException::withMessages([
                'session' => 'Session has already been started.'
            ]);
        }

        // Start the session
        $bookingService->update([
            'started_at' => now(),
            'session_status' => 'in_progress',
            'started_by_actor_type' => get_class($actor),
            'started_by_actor_id' => $actor->id,
        ]);

        // Advance booking state if not already in_progress
        if ($booking->status !== 'in_progress') {
            app(\App\Services\BookingService::class)->transitionStatus(
                $booking,
                'in_progress',
                'staff',
                $actor->id,
                'Service session started'
            );
        }

        $booking->refresh();

        // Invalidate availability cache for this booking's context
        app(\App\Services\AvailabilityEngine::class)->clearAvailabilityCacheForSession(
            $booking->location_id,
            \Carbon\Carbon::parse($booking->date)->format('Y-m-d'),
            $booking->service_id,
            $booking->specialist_id
        );

        return $bookingService->fresh();
    }

    /**
     * Complete a session for a booking service.
     *
     * @param BookingService $bookingService
     * @param mixed $actor
     * @return BookingService
     * @throws ValidationException
     */
    public function completeSession(BookingService $bookingService, $actor): BookingService
    {
        $booking = Booking::find($bookingService->booking_id);

        // Validate specialist owns this booking
        if ($booking->specialist_id === null) {
            throw ValidationException::withMessages([
                'specialist' => 'Booking has no assigned specialist.'
            ]);
        }

        $specialist = Specialist::findOrFail($booking->specialist_id);

        // Authorize actor
        $this->authorizeActorForSession($actor, $booking, $specialist);

        // Validate session is in progress
        if ($bookingService->session_status !== 'in_progress') {
            throw ValidationException::withMessages([
                'session' => "Session is not in progress. Current status: {$bookingService->session_status}"
            ]);
        }

        // Validate session has been started
        if ($bookingService->started_at === null) {
            throw ValidationException::withMessages([
                'session' => 'Session must be started before completion.'
            ]);
        }

        // Validate session has not already been completed
        if ($bookingService->actual_end_at !== null) {
            throw ValidationException::withMessages([
                'session' => 'Session has already been completed.'
            ]);
        }

        // Complete the session
        $bookingService->update([
            'actual_end_at' => now(),
            'session_status' => 'completed',
            'completed_by_actor_type' => get_class($actor),
            'completed_by_actor_id' => $actor->id,
        ]);

        // Are all sessions for this booking completed?
        $allCompleted = !BookingService::where('booking_id', $booking->id)
            ->where('session_status', '!=', 'completed')
            ->exists();
            
        if ($allCompleted && $booking->status !== 'completed') {
            app(\App\Services\BookingService::class)->transitionStatus(
                $booking,
                'completed',
                'staff',
                $actor->id,
                'All service sessions completed'
            );
        }

        $booking->refresh();

        // Invalidate availability cache for this booking's context
        app(\App\Services\AvailabilityEngine::class)->clearAvailabilityCacheForSession(
            $booking->location_id,
            \Carbon\Carbon::parse($booking->date)->format('Y-m-d'),
            $booking->service_id,
            $booking->specialist_id
        );

        return $bookingService->fresh();
    }

    /**
     * Cancel a session for a booking service.
     *
     * @param BookingService $bookingService
     * @param mixed $actor
     * @return BookingService
     * @throws ValidationException
     */
    public function cancelSession(BookingService $bookingService, $actor): BookingService
    {
        $booking = Booking::find($bookingService->booking_id);

        // Validate specialist owns this booking
        if ($booking->specialist_id === null) {
            throw ValidationException::withMessages([
                'specialist' => 'Booking has no assigned specialist.'
            ]);
        }

        $specialist = Specialist::findOrFail($booking->specialist_id);

        // Authorize actor
        $this->authorizeActorForSession($actor, $booking, $specialist);

        // Validate session is in cancellable state
        if (!in_array($bookingService->session_status, ['scheduled', 'in_progress'])) {
            throw ValidationException::withMessages([
                'session' => "Session cannot be cancelled. Current status: {$bookingService->session_status}"
            ]);
        }

        // Validate session has not already been cancelled
        if ($bookingService->session_status === 'cancelled') {
            throw ValidationException::withMessages([
                'session' => 'Session has already been cancelled.'
            ]);
        }

        // Cancel the session
        $bookingService->update([
            'session_status' => 'cancelled',
        ]);

        $booking->refresh();

        // Invalidate availability cache for this booking's context
        app(\App\Services\AvailabilityEngine::class)->clearAvailabilityCacheForSession(
            $booking->location_id,
            \Carbon\Carbon::parse($booking->date)->format('Y-m-d'),
            $booking->service_id,
            $booking->specialist_id
        );

        return $bookingService->fresh();
    }

    /**
     * Authorize the actor (Specialist or Staff User) for this session operation.
     */
    private function authorizeActorForSession($actor, Booking $booking, Specialist $specialist)
    {
        if ($actor instanceof \App\Models\SpecialistAccount) {
            if ($actor->specialist_id !== $specialist->id) {
                throw ValidationException::withMessages([
                    'authorization' => 'You are not the assigned specialist for this booking.'
                ]);
            }
            return;
        }

        if ($actor instanceof \App\Models\User) {
            $providerUser = \App\Models\ProviderUser::where('user_id', $actor->id)
                ->where('provider_id', $booking->provider_id)
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

            if ($booking->location_id && !$authorityService->canAccessLocation($providerUser, $booking->location_id)) {
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
