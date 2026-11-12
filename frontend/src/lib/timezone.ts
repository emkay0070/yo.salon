/**
 * Canonical timezone/date utilities for Yo.Salon frontend.
 *
 * This provides timezone-aware date/time operations that align with
 * the backend TimezoneResolverService architecture.
 *
 * All timezone identifiers must be IANA format (e.g., 'Africa/Kampala', 'Europe/London').
 */

export interface Booking {
  id: string;
  date: string;
  time: string;
  location_id?: string | null;
  provider_id: string;
}

export interface Provider {
  id: string;
  timezone?: string | null;
}

export interface Location {
  id: string;
  timezone?: string | null;
}

export interface User {
  my_view_timezone?: string | null;
}

/**
 * Resolve the effective business timezone for a booking.
 *
 * Hierarchy:
 * 1. Location timezone (if booking has a location)
 * 2. Provider default timezone
 * 3. Application fallback (Africa/Kampala)
 */
export function resolveBusinessTimezone(
  booking: Pick<Booking, 'location_id' | 'provider_id'>,
  location?: Location | null,
  provider?: Provider | null
): string {
  return location?.timezone ?? provider?.timezone ?? 'Africa/Kampala';
}

/**
 * Resolve the user's view timezone preference.
 *
 * Hierarchy:
 * 1. User's my_view_timezone preference
 * 2. Business timezone (as fallback)
 * 3. Application fallback (Africa/Kampala)
 */
export function resolveViewTimezone(
  user?: User | null,
  businessTimezone?: string
): string {
  return user?.my_view_timezone ?? businessTimezone ?? 'Africa/Kampala';
}

/**
 * Get the business-local date key for a booking.
 *
 * This returns the date as it appears in the business timezone,
 * not the UTC date. Used for calendar date matching.
 *
 * @param booking - Booking with date and time
 * @param timezone - Business timezone (IANA identifier)
 * @returns Date in YYYY-MM-DD format (business-local)
 */
export function getBookingDateKey(booking: Pick<Booking, 'date' | 'time'>, timezone: string): string {
  // Handle ISO format dates (e.g., '2026-10-07T00:00:00.000000Z')
  // Extract just the YYYY-MM-DD portion
  const dateStr = booking.date.includes('T') ? booking.date.split('T')[0] : booking.date;

  const scheduledAt = parseBookingDateTime(dateStr, booking.time, timezone);
  return formatDateKey(scheduledAt);
}

/**
 * Parse a booking's date and time in the given timezone.
 *
 * @param date - Date in YYYY-MM-DD format
 * @param time - Time in HH:MM or HH:MM AM/PM format
 * @param timezone - IANA timezone identifier
 * @returns Date object in the given timezone
 */
export function parseBookingDateTime(date: string, time: string, timezone: string): Date {
  const [hours, minutes] = parseTime(time);
  const dateTime = new Date(`${date}T${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`);
  
  // Convert to target timezone
  return new Date(dateTime.toLocaleString('en-US', { timeZone: timezone }));
}

/**
 * Parse time string to hours and minutes.
 * Handles both "HH:MM" and "HH:MM AM/PM" formats.
 */
function parseTime(time: string): [number, number] {
  if (time.includes('AM') || time.includes('PM')) {
    const [timePart, period] = time.split(' ');
    const [hStr, mStr] = timePart.split(':');
    let hours = parseInt(hStr, 10);
    const minutes = parseInt(mStr || '0', 10);
    
    if (period === 'PM' && hours !== 12) hours += 12;
    if (period === 'AM' && hours === 12) hours = 0;
    
    return [hours, minutes];
  }
  
  const [hStr, mStr] = time.split(':');
  return [parseInt(hStr, 10), parseInt(mStr || '0', 10)];
}

/**
 * Format a Date to YYYY-MM-DD key (timezone-agnostic).
 * Used for calendar date matching.
 */
export function formatDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Format a booking date for display in the user's view timezone.
 *
 * @param booking - Booking with date and time
 * @param businessTimezone - Business timezone
 * @param viewTimezone - User's view timezone (null = use business timezone)
 * @returns Formatted date (e.g., "Monday, October 7, 2026")
 */
export function formatBookingDate(
  booking: Pick<Booking, 'date' | 'time'>,
  businessTimezone: string,
  viewTimezone?: string | null
): string {
  const timezone = viewTimezone ?? businessTimezone;
  const scheduledAt = parseBookingDateTime(booking.date, booking.time, businessTimezone);
  
  return scheduledAt.toLocaleDateString('en-US', {
    timeZone: timezone,
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format a booking time for display in the user's view timezone.
 *
 * @param booking - Booking with date and time
 * @param businessTimezone - Business timezone
 * @param viewTimezone - User's view timezone (null = use business timezone)
 * @returns Formatted time (e.g., "10:45 AM")
 */
export function formatBookingTime(
  booking: Pick<Booking, 'date' | 'time'>,
  businessTimezone: string,
  viewTimezone?: string | null
): string {
  const timezone = viewTimezone ?? businessTimezone;
  const scheduledAt = parseBookingDateTime(booking.date, booking.time, businessTimezone);
  
  return scheduledAt.toLocaleTimeString('en-US', {
    timeZone: timezone,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Get the current business date in the given timezone.
 *
 * @param timezone - IANA timezone identifier
 * @returns Date in YYYY-MM-DD format
 */
export function getCurrentBusinessDate(timezone: string): string {
  const now = new Date();
  return formatDateKey(new Date(now.toLocaleString('en-US', { timeZone: timezone })));
}

/**
 * Get the current business time in the given timezone.
 *
 * @param timezone - IANA timezone identifier
 * @returns Date object in business timezone
 */
export function getCurrentBusinessTime(timezone: string): Date {
  const now = new Date();
  return new Date(now.toLocaleString('en-US', { timeZone: timezone }));
}
