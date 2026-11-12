<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\Location;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Process booking operational time states.
 *
 * This command moves bookings through their operational time states based on
 * the scheduled appointment time. It is idempotent and can be run repeatedly
 * without duplicating state transitions or activities.
 *
 * Operational states:
 * - upcoming: Before 30 minutes before scheduled time
 * - due_soon: Within 30 minutes before scheduled time
 * - grace_period: At or after scheduled time, before grace period ends
 * - needs_resolution: At or after grace period ends
 * - resolved: Manually resolved (not processed by this command)
 */
class ProcessBookingTimeStates extends Command
{
    protected $signature = 'bookings:process-time-states';
    protected $description = 'Process booking operational time states based on scheduled appointment time';

    private const DUE_SOON_MINUTES = 30;
    private const DEFAULT_GRACE_PERIOD_MINUTES = 15;

    public function handle(): int
    {
        $this->info('Processing booking time states...');

        $processed = 0;
        $transitions = [
            'upcoming_to_due_soon' => 0,
            'upcoming_to_grace_period' => 0,
            'upcoming_to_needs_resolution' => 0,
            'due_soon_to_grace_period' => 0,
            'due_soon_to_needs_resolution' => 0,
            'grace_period_to_needs_resolution' => 0,
        ];

        // Query bookings that could potentially transition
        // Only process active booking statuses that represent valid appointments
        // Include historical bookings to ensure they transition to needs_resolution
        $bookings = Booking::with(['location', 'provider'])
            ->whereIn('status', ['confirmed', 'pending_payment'])
            ->where('operational_state', '!=', 'resolved')
            ->where(function($query) {
                // Bookings within next day (upcoming and historical)
                $query->where('date', '<=', Carbon::now()->addDays(1)->toDateString())
                    ->orWhere(function($q) {
                        // Also include historical bookings that are still in upcoming/due_soon state
                        $q->whereIn('operational_state', ['upcoming', 'due_soon'])
                            ->where('date', '<', Carbon::now()->toDateString());
                    });
            })
            ->get();

        $this->info("Found {$bookings->count()} bookings to evaluate.");

        foreach ($bookings as $booking) {
            $result = $this->processBooking($booking);
            
            if ($result !== null) {
                $processed++;
                $transitions[$result]++;
            }
        }

        $this->info("Processed {$processed} bookings.");
        $this->info("Transitions: " . json_encode($transitions));

        return self::SUCCESS;
    }

    /**
     * Process a single booking's time state.
     *
     * @return string|null Transition type, or null if no transition occurred
     */
    private function processBooking(Booking $booking): ?string
    {
        // Use canonical timezone resolver
        $timezoneResolver = app(\App\Services\TimezoneResolverService::class);
        $timezone = $timezoneResolver->resolveBusinessTimezone($booking);

        // Parse scheduled appointment datetime in provider timezone
        // Parse date and time separately to avoid parsing issues
        $scheduledDate = Carbon::parse($booking->date, $timezone)->startOfDay();
        $scheduledAt = $scheduledDate->setTimeFromTimeString($booking->time);
        $now = Carbon::now($timezone);
        
        // Get grace period configuration (default 15 minutes)
        $gracePeriodMinutes = $this->getGracePeriodMinutes($booking);
        $graceEndsAt = $scheduledAt->copy()->addMinutes($gracePeriodMinutes);
        
        $currentState = $booking->operational_state;
        $newState = null;

        // Determine target state based on time
        if ($now->lt($scheduledAt->copy()->subMinutes(self::DUE_SOON_MINUTES))) {
            // More than 30 minutes before scheduled time
            $newState = 'upcoming';
        } elseif ($now->lt($scheduledAt)) {
            // Within 30 minutes before scheduled time
            $newState = 'due_soon';
        } elseif ($now->lt($graceEndsAt)) {
            // Within grace period
            $newState = 'grace_period';
        } else {
            // Grace period expired
            $newState = 'needs_resolution';
        }

        // Skip if already in target state
        if ($currentState === $newState) {
            return null;
        }

        // Skip terminal operational states (resolved bookings stay resolved)
        if ($currentState === 'resolved') {
            return null;
        }

        // Skip if booking has been operationally acted upon (arrived, waiting, in_progress)
        if (in_array($booking->status, ['arrived', 'waiting', 'in_progress'])) {
            return null;
        }

        // Perform transition
        return $this->transitionState($booking, $newState, $scheduledAt, $graceEndsAt);
    }

    /**
     * Transition a booking to a new operational state.
     *
     * @return string Transition type
     */
    private function transitionState(
        Booking $booking,
        string $newState,
        Carbon $scheduledAt,
        Carbon $graceEndsAt
    ): string {
        $previousState = $booking->operational_state;
        $transitionKey = "{$previousState}_to_{$newState}";

        $updateData = ['operational_state' => $newState];

        // Set grace period timestamps when entering grace_period
        // IMPORTANT: These are anchored to scheduled_at, not current time
        if ($newState === 'grace_period' && $booking->grace_period_started_at === null) {
            $updateData['grace_period_started_at'] = $scheduledAt;
            $updateData['grace_period_ends_at'] = $graceEndsAt;
        }

        DB::transaction(function () use ($booking, $updateData, $previousState, $newState) {
            $booking->update($updateData);

            // Create activity record
            $activityType = $this->getActivityTypeForTransition($newState);
            $activityTitle = $this->getActivityTitleForTransition($newState);
            
            BookingActivity::create([
                'booking_id' => $booking->id,
                'type' => $activityType,
                'title' => $activityTitle,
                'description' => "Operational state changed from {$previousState} to {$newState}",
                'data' => [
                    'previous_operational_state' => $previousState,
                    'new_operational_state' => $newState,
                    'grace_period_started_at' => $booking->grace_period_started_at?->toIso8601String(),
                    'grace_period_ends_at' => $booking->grace_period_ends_at?->toIso8601String(),
                ],
                'actor_type' => 'system',
                'actor_id' => null,
            ]);
        });

        $this->line("Booking {$booking->id}: {$previousState} -> {$newState}");

        return $transitionKey;
    }

    /**
     * Get grace period in minutes for a booking.
     * Currently uses default 15 minutes.
     * Future: Read from provider booking configuration.
     */
    private function getGracePeriodMinutes(Booking $booking): int
    {
        // TODO: Read from provider booking configuration when available
        return self::DEFAULT_GRACE_PERIOD_MINUTES;
    }

    /**
     * Get activity type for operational state transition.
     */
    private function getActivityTypeForTransition(string $newState): string
    {
        return match ($newState) {
            'due_soon' => 'operational_state_due_soon',
            'grace_period' => 'grace_period_started',
            'needs_resolution' => 'grace_period_expired',
            default => 'operational_state_changed',
        };
    }

    /**
     * Get activity title for operational state transition.
     */
    private function getActivityTitleForTransition(string $newState): string
    {
        return match ($newState) {
            'due_soon' => 'Booking is due soon',
            'grace_period' => 'Grace period started',
            'needs_resolution' => 'Grace period expired - needs resolution',
            default => "Operational state changed to {$newState}",
        };
    }
}
