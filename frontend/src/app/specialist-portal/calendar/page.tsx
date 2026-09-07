'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  Clock,
  Coffee,
  Plane,
  ChevronLeft,
  ChevronRight,
  Loader2,
  AlertCircle,
  Scissors,
  User,
  MapPin,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { apiClient } from '@/lib/api-client';

const DAY_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Returns the Monday that starts the week containing `anchor`. */
function getWeekMonday(anchor: Date): Date {
  const d = new Date(anchor);
  const day = d.getDay();
  const offset = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + offset);
  d.setHours(0, 0, 0, 0);
  return d;
}

function toYMD(d: Date) {
  return d.toISOString().split('T')[0];
}

function formatMonthLabel(monday: Date) {
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
  if (monday.getMonth() === sunday.getMonth()) {
    return monday.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }
  return `${monday.toLocaleDateString('en-US', opts)} – ${sunday.toLocaleDateString('en-US', { ...opts, year: 'numeric' })}`;
}

function formatTime(t: string | null | undefined) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM';
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${ampm}`;
}

const STATUS_COLOUR: Record<string, string> = {
  pending:   'bg-blue-500/20 text-blue-300 border-blue-500/30',
  confirmed: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
  completed: 'bg-slate-500/20 text-slate-400 border-slate-500/30',
  cancelled: 'bg-red-500/20 text-red-400 border-red-500/30',
  no_show:   'bg-orange-500/20 text-orange-400 border-orange-500/30',
};

type Day = {
  date: string;
  day_name: string;
  is_working: boolean;
  is_closed: boolean;
  has_time_off: boolean;
  has_override: boolean;
  open_time: string | null;
  close_time: string | null;
  break_start: string | null;
  break_end: string | null;
  time_off: { id: string; reason: string | null; all_day: boolean }[];
  salon: { id: string; name: string } | null;
};

type Booking = {
  id: string;
  date: string;
  time: string;
  status: string;
  customer: string;
  service: string;
  salon: { id: string; name: string } | null;
};

type CalendarData = {
  week_start: string;
  week_end: string;
  days: Day[];
  bookings: Booking[];
};

export default function CalendarPage() {
  const { specialist } = useSpecialistAuth();
  const [anchor, setAnchor] = useState(new Date());

  const monday    = useMemo(() => getWeekMonday(anchor), [anchor]);
  const weekStart = toYMD(monday);
  const todayYMD  = toYMD(new Date());

  const { data, isLoading, isError } = useQuery<CalendarData>({
    queryKey: ['specialist-calendar', weekStart],
    queryFn: async () => {
      return await apiClient.get(`/v1/specialist-portal/calendar?week_start=${weekStart}`);
    },
    enabled: !!specialist,
  });

  const goBack    = () => { const d = new Date(anchor); d.setDate(d.getDate() - 7); setAnchor(d); };
  const goForward = () => { const d = new Date(anchor); d.setDate(d.getDate() + 7); setAnchor(d); };
  const goToday   = () => setAnchor(new Date());

  /** Bookings indexed by date string */
  const bookingsByDate = useMemo(() => {
    const map: Record<string, Booking[]> = {};
    data?.bookings?.forEach(b => { (map[b.date] ??= []).push(b); });
    return map;
  }, [data]);

  /** Build the 7-day list — use API days if available, otherwise derive from anchor */
  const days: (Day | null)[] = useMemo(() => {
    if (data?.days?.length) return data.days;
    // Fallback skeleton while loading (or no assignment)
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(monday);
      d.setDate(monday.getDate() + i);
      return null; // null means "no data yet"
    });
  }, [data, monday]);

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* ── Header ── */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Calendar</h1>
          <p className="text-text-secondary">Your weekly schedule at a glance</p>
        </div>

        <div className="flex items-center gap-1 bg-card border border-border-light rounded-xl p-1">
          <button
            id="cal-prev-week"
            onClick={goBack}
            className="p-2 rounded-lg hover:bg-surface text-text-secondary transition-colors"
          >
            <ChevronLeft className="w-4 h-4" />
          </button>
          <button
            id="cal-today"
            onClick={goToday}
            className="px-3 py-1.5 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface transition-colors"
          >
            Today
          </button>
          <button
            id="cal-next-week"
            onClick={goForward}
            className="p-2 rounded-lg hover:bg-surface text-text-secondary transition-colors"
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      <p className="text-sm font-medium text-text-secondary -mt-2">{formatMonthLabel(monday)}</p>

      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center py-24">
          <Loader2 className="w-8 h-8 text-gold animate-spin" />
        </div>
      )}

      {/* Error */}
      {isError && (
        <div className="bg-card border border-border-light rounded-2xl p-12 text-center">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-4" />
          <p className="text-text-secondary">Failed to load calendar. Please try again.</p>
        </div>
      )}

      {/* ── Week Grid ── */}
      {!isLoading && !isError && (
        <div className="grid grid-cols-1 sm:grid-cols-7 gap-3">
          {(data?.days?.length ? data.days : Array.from({ length: 7 }, (_, i) => {
            const d = new Date(monday);
            d.setDate(monday.getDate() + i);
            const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            return {
              date: toYMD(d),
              day_name: DAYS[i],
              is_working: false, is_closed: true, has_time_off: false, has_override: false,
              open_time: null, close_time: null, break_start: null, break_end: null,
              time_off: [], salon: null,
            } as Day;
          })).map((day, i) => {
            const ymd      = day.date;
            const isToday  = ymd === todayYMD;
            const dayNum   = new Date(ymd + 'T12:00:00').getDate();
            const bookings = bookingsByDate[ymd] ?? [];

            return (
              <motion.div
                key={ymd}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.04 }}
                className={`rounded-2xl border flex flex-col min-h-[190px] transition-colors ${
                  isToday
                    ? 'border-gold/60 bg-card shadow-lg shadow-gold/10'
                    : 'border-border-light bg-card'
                } ${day.is_closed && !day.has_time_off ? 'opacity-40' : ''}`}
              >
                {/* Day header */}
                <div className={`flex items-center justify-between px-3 pt-3 pb-1.5 ${isToday ? 'bg-gold/10 rounded-t-2xl' : ''}`}>
                  <span className="text-xs font-semibold text-text-secondary uppercase tracking-widest">
                    {DAY_SHORT[i]}
                  </span>
                  <span className={`text-xl font-bold leading-none ${isToday ? 'text-gold' : 'text-text-primary'}`}>
                    {dayNum}
                  </span>
                </div>

                {/* Resolved working hours (from Scheduling Domain) */}
                {day.is_working && day.open_time && (
                  <div className="px-3 pb-1 space-y-0.5">
                    <span className="flex items-center gap-1 text-xs text-text-secondary">
                      <Clock className="w-3 h-3 flex-shrink-0" />
                      {formatTime(day.open_time)} – {formatTime(day.close_time)}
                    </span>
                    {day.break_start && (
                      <span className="flex items-center gap-1 text-xs text-amber-400/70">
                        <Coffee className="w-3 h-3 flex-shrink-0" />
                        {formatTime(day.break_start)} – {formatTime(day.break_end)}
                      </span>
                    )}
                    {day.has_override && (
                      <span className="text-[10px] text-purple-400/80 font-medium">Modified</span>
                    )}
                  </div>
                )}

                {/* Closed (off day) */}
                {day.is_closed && !day.has_time_off && (
                  <div className="flex-1 flex items-center justify-center">
                    <span className="text-xs text-text-secondary/50">Off</span>
                  </div>
                )}

                {/* Time off */}
                {day.time_off.length > 0 && (
                  <div className="px-3 pb-1 space-y-1">
                    {day.time_off.map(t => (
                      <div key={t.id} className="flex items-center gap-1 text-xs bg-blue-400/10 text-blue-300 rounded-md px-2 py-1">
                        <Plane className="w-3 h-3 flex-shrink-0" />
                        <span className="truncate">{t.reason ?? 'Time Off'}</span>
                      </div>
                    ))}
                  </div>
                )}

                {/* Bookings for this day */}
                <div className="flex-1 px-2 pb-2 space-y-1.5 mt-1 overflow-hidden">
                  {bookings.map(b => {
                    const colour = STATUS_COLOUR[b.status?.toLowerCase()] ?? STATUS_COLOUR.pending;
                    return (
                      <div key={b.id} className={`rounded-lg border px-2 py-1.5 text-xs ${colour}`}>
                        <p className="font-semibold truncate flex items-center gap-1">
                          <User className="w-2.5 h-2.5 flex-shrink-0" />
                          {b.customer}
                        </p>
                        <p className="truncate flex items-center gap-1 opacity-80">
                          <Scissors className="w-2.5 h-2.5 flex-shrink-0" />
                          {b.service}
                        </p>
                        <p className="flex items-center gap-1 opacity-70 mt-0.5">
                          <Clock className="w-2.5 h-2.5 flex-shrink-0" />
                          {formatTime(b.time)}
                        </p>
                        {/* Salon context — invaluable for multi-salon specialists */}
                        {b.salon && (
                          <p className="flex items-center gap-1 opacity-60 mt-0.5 truncate">
                            <MapPin className="w-2.5 h-2.5 flex-shrink-0" />
                            {b.salon.name}
                          </p>
                        )}
                      </div>
                    );
                  })}

                  {day.is_working && bookings.length === 0 && !day.has_time_off && (
                    <div className="flex-1 flex items-end pb-1">
                      <span className="text-[10px] text-text-secondary/40 pl-1">No bookings</span>
                    </div>
                  )}
                </div>
              </motion.div>
            );
          })}
        </div>
      )}

      {/* ── No Assignment State ── */}
      {!isLoading && !isError && data && data.days.length === 0 && (
        <div className="bg-card border border-border-light rounded-2xl p-10 text-center">
          <Clock className="w-10 h-10 text-text-secondary mx-auto mb-3" />
          <p className="text-text-secondary font-medium">No schedule configured yet</p>
          <p className="text-sm text-text-secondary mt-1">
            Complete your availability setup in onboarding to see your weekly schedule here.
          </p>
        </div>
      )}
    </div>
  );
}
