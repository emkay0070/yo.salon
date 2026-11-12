<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;

/**
 * Canonical timezone resolver for Yo.Salon.
 *
 * This service provides timezone-aware date/time operations for:
 * - Business operational timezone (Location or Provider)
 * - User view timezone preference
 * - UTC conversions for storage
 *
 * All timezone identifiers must be IANA format (e.g., 'Africa/Kampala', 'Europe/London').
 */
class TimezoneResolverService
{
    /**
     * Resolve the effective business timezone for a booking.
     *
     * Hierarchy:
     * 1. Location timezone (if booking has a location)
     * 2. Provider default timezone
     * 3. Application fallback (Africa/Kampala)
     *
     * @param Booking $booking
     * @return string IANA timezone identifier
     */
    public function resolveBusinessTimezone(Booking $booking): string
    {
        return $booking->location?->timezone
            ?? $booking->provider?->timezone
            ?? config('app.timezone', 'Africa/Kampala');
    }

    /**
     * Resolve the effective business timezone for a provider/location context.
     *
     * @param Provider $provider
     * @param Location|null $location
     * @return string IANA timezone identifier
     */
    public function resolveBusinessTimezoneForContext(Provider $provider, ?Location $location = null): string
    {
        return $location?->timezone
            ?? $provider->timezone
            ?? config('app.timezone', 'Africa/Kampala');
    }

    /**
     * Resolve the effective business timezone by provider_id and location_id.
     *
     * Hierarchy:
     * 1. Location timezone (if location_id provided and exists)
     * 2. Provider default timezone (if provider_id provided and exists)
     * 3. Application fallback (Africa/Kampala)
     *
     * @param string|null $providerId
     * @param string|null $locationId
     * @return string IANA timezone identifier
     */
    public function resolveBusinessTimezoneById(?string $providerId, ?string $locationId = null): string
    {
        if ($locationId) {
            $location = Location::find($locationId);
            if ($location?->timezone) {
                return $location->timezone;
            }
        }

        if ($providerId) {
            $provider = Provider::find($providerId);
            if ($provider?->timezone) {
                return $provider->timezone;
            }
        }

        return config('app.timezone', 'Africa/Kampala');
    }

    /**
     * Resolve the user's view timezone preference.
     *
     * Hierarchy:
     * 1. User's my_view_timezone preference
     * 2. Business timezone (as fallback)
     * 3. Application fallback (Africa/Kampala)
     *
     * @param User|null $user
     * @param string|null $businessTimezone
     * @return string IANA timezone identifier
     */
    public function resolveViewTimezone(?User $user, ?string $businessTimezone = null): string
    {
        if ($user && $user->my_view_timezone) {
            return $user->my_view_timezone;
        }

        return $businessTimezone ?? config('app.timezone', 'Africa/Kampala');
    }

    /**
     * Convert a local date/time in a given timezone to UTC.
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param string $time Time in HH:MM format
     * @param string $timezone IANA timezone identifier
     * @return Carbon UTC Carbon instance
     */
    public function toUtc(string $date, string $time, string $timezone): Carbon
    {
        $local = Carbon::parse("{$date} {$time}", $timezone);
        return $local->utc();
    }

    /**
     * Convert a UTC datetime to local time in a given timezone.
     *
     * @param Carbon|string $utc UTC datetime
     * @param string $timezone IANA timezone identifier
     * @return Carbon Local Carbon instance
     */
    public function toLocal(Carbon|string $utc, string $timezone): Carbon
    {
        $utc = is_string($utc) ? Carbon::parse($utc, 'UTC') : $utc->copy()->utc();
        return $utc->setTimezone($timezone);
    }

    /**
     * Get the current business time in the given timezone.
     *
     * @param string $timezone IANA timezone identifier
     * @return Carbon Current time in business timezone
     */
    public function getCurrentBusinessTime(string $timezone): Carbon
    {
        return Carbon::now($timezone);
    }

    /**
     * Get the current business date in the given timezone.
     *
     * @param string $timezone IANA timezone identifier
     * @return string Date in YYYY-MM-DD format
     */
    public function getCurrentBusinessDate(string $timezone): string
    {
        return Carbon::now($timezone)->toDateString();
    }

    /**
     * Get the business-local date key for a booking.
     *
     * This returns the date as it appears in the business timezone,
     * not the UTC date. Used for calendar date matching.
     *
     * @param Booking $booking
     * @return string Date in YYYY-MM-DD format (business-local)
     */
    public function getBookingDateKey(Booking $booking): string
    {
        $timezone = $this->resolveBusinessTimezone($booking);
        $scheduledAt = Carbon::parse("{$booking->date} {$booking->time}", $timezone);
        return $scheduledAt->toDateString();
    }

    /**
     * Format a booking date for display in the user's view timezone.
     *
     * @param Booking $booking
     * @param string|null $viewTimezone User's view timezone (null = use business timezone)
     * @return string Formatted date
     */
    public function formatBookingDate(Booking $booking, ?string $viewTimezone = null): string
    {
        $businessTimezone = $this->resolveBusinessTimezone($booking);
        $timezone = $viewTimezone ?? $businessTimezone;

        $scheduledAt = Carbon::parse("{$booking->date} {$booking->time}", $businessTimezone);
        return $scheduledAt->setTimezone($timezone)->format('l, F j, Y');
    }

    /**
     * Format a booking time for display in the user's view timezone.
     *
     * @param Booking $booking
     * @param string|null $viewTimezone User's view timezone (null = use business timezone)
     * @return string Formatted time (e.g., "10:45 AM")
     */
    public function formatBookingTime(Booking $booking, ?string $viewTimezone = null): string
    {
        $businessTimezone = $this->resolveBusinessTimezone($booking);
        $timezone = $viewTimezone ?? $businessTimezone;

        $scheduledAt = Carbon::parse("{$booking->date} {$booking->time}", $businessTimezone);
        return $scheduledAt->setTimezone($timezone)->format('g:i A');
    }
}
