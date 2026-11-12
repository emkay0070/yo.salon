'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface Booking {
  id: string;
  customerName: string;
  service: string;
  staffName: string;
  time: string;
  date: string;
  originalDate?: string; // YYYY-MM-DD for proper date comparisons
  endTime: string;
  duration: number;
  status: string; // Lifecycle status: pending, pending_payment, confirmed, arrived, waiting, in_progress, completed, cancelled, no_show, rescheduled
  operational_state?: string; // Operational state: upcoming, due_soon, grace_period, needs_resolution, resolved
  grace_period_started_at?: string | null;
  grace_period_ends_at?: string | null;
  price: number;
  phone: string;
  notes?: string;
  resolution?: {
    outcome: string;
    reason_code: string;
    notes?: string;
    follow_up_required: boolean;
    follow_up_status?: string;
  } | null;
}

interface Staff {
  id: string;
  name: string;
}

interface TimelineViewProps {
  bookings: Booking[];
  staff: Staff[];
  onBookingClick: (booking: Booking) => void;
  currentUserRole?: string;
  currentUserName?: string;
}

const hours = Array.from({ length: 12 }, (_, i) => i + 9); // 9 AM → 8 PM

// Helper: Calculate minutes until appointment time
function getMinutesUntil(bookingTime: string, bookingDate: string): number {
  const now = new Date();
  const [hours, minutes] = bookingTime.split(':').map(Number);
  const [hSuffix] = bookingTime.split(' ');
  
  // Parse time (handle both "09:30" and "09:30 AM" formats)
  let hour = hours;
  if (bookingTime.includes('AM') || bookingTime.includes('PM')) {
    const [h, m] = bookingTime.split(' ')[0].split(':').map(Number);
    hour = h;
    if (bookingTime.includes('PM') && hour !== 12) hour += 12;
    if (bookingTime.includes('AM') && hour === 12) hour = 0;
  }
  
  const bookingDateTime = new Date(now);
  bookingDateTime.setHours(hour, minutes || 0, 0, 0);
  
  return Math.floor((bookingDateTime.getTime() - now.getTime()) / 60000);
}

// Helper: Get operational state display text
function getOperationalStateDisplay(booking: Booking): { text: string; color: string } | null {
  if (!booking.operational_state) return null;

  switch (booking.operational_state) {
    case 'upcoming':
      const minsUntil = getMinutesUntil(booking.time, booking.date);
      if (minsUntil > 15) {
        return { text: `Starts in ${minsUntil} min`, color: 'text-text-secondary' };
      } else if (minsUntil > 0) {
        return { text: `Starts in ${minsUntil} min`, color: 'text-amber-400' };
      }
      return null;
    case 'due_soon':
      return { text: 'Due soon', color: 'text-amber-400' };
    case 'grace_period':
      if (booking.grace_period_ends_at) {
        const now = new Date();
        const endsAt = new Date(booking.grace_period_ends_at);
        const minsRemaining = Math.floor((endsAt.getTime() - now.getTime()) / 60000);
        if (minsRemaining > 0) {
          return { text: `${minsRemaining} min remaining`, color: 'text-amber-400' };
        }
      }
      return { text: 'Grace period', color: 'text-amber-400' };
    case 'needs_resolution':
      if (booking.grace_period_ends_at) {
        const now = new Date();
        const endsAt = new Date(booking.grace_period_ends_at);
        const minsOverdue = Math.floor((now.getTime() - endsAt.getTime()) / 60000);
        return { text: `${minsOverdue} min overdue`, color: 'text-red-400' };
      }
      return { text: 'Needs attention', color: 'text-red-400' };
    default:
      return null;
  }
}

function getStatusStyle(status: string) {
  switch (status) {
    case 'confirmed':  return 'bg-emerald-500/10 border-emerald-500/25 text-emerald-400';
    case 'checked_in': return 'bg-blue-500/10 border-blue-500/25 text-blue-400';
    case 'in_service': return 'bg-gold/10 border-gold/25 text-gold';
    case 'completed':  return 'bg-card border-border-light text-text-secondary';
    case 'cancelled':  return 'bg-red-500/10 border-red-500/25 text-red-400';
    default:           return 'bg-card border-border-light text-text-secondary';
  }
}

function getStatusDot(status: string) {
  switch (status) {
    case 'confirmed':  return 'bg-emerald-400';
    case 'checked_in': return 'bg-blue-400';
    case 'in_service': return 'bg-gold';
    case 'completed':  return 'bg-text-secondary';
    case 'cancelled':  return 'bg-red-400';
    default:           return 'bg-text-secondary';
  }
}

function getBookingForSlot(bookings: Booking[], staffName: string, hour: number) {
  return bookings.find(b => {
    // Parse time in "HH:MM AM/PM" format to 24-hour integer
    let bookingHour = 0;
    if (b.time.includes('AM') || b.time.includes('PM')) {
      const [timePart, period] = b.time.split(' ');
      const [hStr] = timePart.split(':');
      bookingHour = parseInt(hStr, 10);
      if (period === 'PM' && bookingHour !== 12) bookingHour += 12;
      if (period === 'AM' && bookingHour === 12) bookingHour = 0;
    } else {
      bookingHour = parseInt(b.time.split(':')[0], 10);
    }
    return b.staffName === staffName && bookingHour === hour;
  });
}

// ─── Single-column view (mobile) ─────────────────────────────────────────────
function SingleColumnTimeline({
  staff,
  bookings,
  onBookingClick,
}: {
  staff: Staff[];
  bookings: Booking[];
  onBookingClick: (b: Booking) => void;
}) {
  const [activeIdx, setActiveIdx] = useState(0);
  const [direction, setDirection] = useState(0);
  const [currentHour, setCurrentHour] = useState(new Date().getHours());

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentHour(new Date().getHours());
    }, 60000); // Update every minute
    return () => clearInterval(timer);
  }, []);

  const go = (dir: number) => {
    setDirection(dir);
    setActiveIdx(i => Math.max(0, Math.min(staff.length - 1, i + dir)));
  };

  const current = staff[activeIdx];

  return (
    <div className="bg-surface/50 border border-border-light rounded-2xl overflow-hidden backdrop-blur-xl">
      {/* Staff switcher header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-border-light bg-surface/30">
        <button
          onClick={() => go(-1)}
          disabled={activeIdx === 0}
          className="w-9 h-9 flex items-center justify-center rounded-xl border border-border-light bg-surface/50 disabled:opacity-30 active:scale-95 transition-all"
        >
          <ChevronLeft className="w-4 h-4 text-text-secondary" />
        </button>

        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-full bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center shadow-sm">
            <span className="text-xs font-bold text-obsidian">{current?.name[0]}</span>
          </div>
          <div className="text-center">
            <p className="text-text-primary font-semibold text-sm">{current?.name}</p>
            <p className="text-text-muted text-[10px]">
              {activeIdx + 1} of {staff.length}
            </p>
          </div>
        </div>

        <button
          onClick={() => go(1)}
          disabled={activeIdx === staff.length - 1}
          className="w-9 h-9 flex items-center justify-center rounded-xl border border-border-light bg-surface/50 disabled:opacity-30 active:scale-95 transition-all"
        >
          <ChevronRight className="w-4 h-4 text-text-secondary" />
        </button>
      </div>

      {/* Dot pagination */}
      {staff.length > 1 && (
        <div className="flex items-center justify-center gap-1.5 pt-2.5 pb-1">
          {staff.map((_, i) => (
            <button
              key={i}
              onClick={() => { setDirection(i > activeIdx ? 1 : -1); setActiveIdx(i); }}
              className={`rounded-full transition-all ${i === activeIdx ? 'w-4 h-1.5 bg-gold' : 'w-1.5 h-1.5 bg-border-medium'}`}
            />
          ))}
        </div>
      )}

      {/* Time slots */}
      <AnimatePresence mode="wait" initial={false}>
        <motion.div
          key={current?.id}
          initial={{ opacity: 0, x: direction * 40 }}
          animate={{ opacity: 1, x: 0 }}
          exit={{ opacity: 0, x: direction * -40 }}
          transition={{ type: 'spring', stiffness: 380, damping: 32 }}
          className="p-3 space-y-1"
        >
          {hours.map(hour => {
            const booking = current ? getBookingForSlot(bookings, current.name, hour) : null;
            const isNow = hour === currentHour;
            return (
              <div key={hour} className="flex items-stretch gap-3 min-h-[56px]">
                {/* Time label */}
                <div className="w-12 flex-shrink-0 flex items-start pt-1">
                  <span className={`text-xs font-medium ${isNow ? 'text-gold font-bold' : 'text-text-muted'}`}>
                    {isNow ? 'NOW' : (hour > 12 ? `${hour - 12}PM` : hour === 12 ? '12PM' : `${hour}AM`)}
                  </span>
                </div>

                {/* Divider + slot */}
                <div className="flex-1 relative">
                  {isNow && (
                    <div className="absolute top-3 left-0 right-0 z-20">
                      <div className="flex items-center gap-2">
                        <div className="w-1.5 h-1.5 rounded-full bg-gold animate-pulse" />
                        <div className="flex-1 h-px bg-gradient-to-r from-gold via-gold/50 to-transparent" />
                      </div>
                    </div>
                  )}
                  <div className={`absolute top-3 left-0 right-0 border-t ${isNow ? 'border-transparent' : 'border-border-light/40'}`} />
                  {booking ? (
                    <motion.button
                      whileTap={{ scale: 0.97 }}
                      onClick={() => onBookingClick(booking)}
                      className={`relative z-10 w-full text-left p-3 rounded-xl border cursor-pointer transition-all shadow-sm ${getStatusStyle(booking.status)} ${isNow ? 'ring-2 ring-gold/50' : ''}`}
                    >
                      <div className="flex items-start justify-between mb-1">
                        <div className="flex items-center gap-2">
                          <div className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${getStatusDot(booking.status)}`} />
                          <span className="text-text-primary font-semibold text-sm truncate">{booking.customerName}</span>
                        </div>
                        <span className="text-text-secondary text-[10px]">{booking.duration}min</span>
                      </div>
                      <p className="text-xs truncate pl-3.5 opacity-80">{booking.service}</p>
                      <p className="text-xs pl-3.5 opacity-60 mt-0.5">{booking.time} · {booking.staffName}</p>
                      {(() => {
                        const opState = getOperationalStateDisplay(booking);
                        return opState ? (
                          <p className={`text-[10px] pl-3.5 mt-1 ${opState.color}`}>{opState.text}</p>
                        ) : null;
                      })()}
                    </motion.button>
                  ) : (
                    <div 
                      className={`relative z-10 w-full h-[48px] rounded-xl border border-dashed flex items-center justify-center group cursor-pointer transition-all ${isNow ? 'border-gold/30 bg-gold/5' : 'border-border-medium hover:border-gold/30 hover:bg-gold/5'}`}
                    >
                      <div className="w-6 h-6 rounded-full bg-gold/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <div className="w-2 h-0.5 bg-gold rounded-full" />
                        <div className="w-0.5 h-2 bg-gold rounded-full absolute" />
                      </div>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </motion.div>
      </AnimatePresence>

    </div>
  );
}

// ─── Multi-column view (desktop) ─────────────────────────────────────────────
function MultiColumnTimeline({
  staff,
  bookings,
  onBookingClick,
}: {
  staff: Staff[];
  bookings: Booking[];
  onBookingClick: (b: Booking) => void;
}) {
  const [currentHour, setCurrentHour] = useState(new Date().getHours());

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentHour(new Date().getHours());
    }, 60000); // Update every minute
    return () => clearInterval(timer);
  }, []);

  return (
    <div className="bg-surface/50 border border-border-light rounded-2xl p-4 backdrop-blur-xl">
      {/* Staff header row */}
      <div className="flex mb-3">
        <div className="w-16 flex-shrink-0" />
        {staff.map(s => (
          <div key={s.id} className="flex-1 flex items-center justify-center gap-2 pb-3 border-b border-border-light">
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center shadow-sm">
              <span className="text-xs font-bold text-obsidian">{s.name[0]}</span>
            </div>
            <span className="text-text-primary font-medium text-sm">{s.name}</span>
          </div>
        ))}
      </div>

      {/* Grid */}
      <div className="space-y-1">
        {hours.map(hour => {
          const isNow = hour === currentHour;
          return (
            <div key={hour} className="flex items-stretch min-h-[52px]">
              <div className="w-16 flex-shrink-0 flex items-start pt-1">
                <span className={`text-xs font-medium ${isNow ? 'text-gold font-bold' : 'text-text-muted'}`}>
                  {isNow ? 'NOW' : (hour > 12 ? `${hour - 12}PM` : hour === 12 ? '12PM' : `${hour}AM`)}
                </span>
              </div>
              {staff.map(s => {
                const booking = getBookingForSlot(bookings, s.name, hour);
                return (
                  <div key={s.id} className="flex-1 px-1 relative">
                    {isNow && (
                      <div className="absolute top-3 left-1 right-1 z-20">
                        <div className="h-px bg-gradient-to-r from-transparent via-gold/50 to-gold" />
                      </div>
                    )}
                    <div className={`absolute top-3 left-1 right-1 border-t ${isNow ? 'border-transparent' : 'border-border-light/40'}`} />
                    {booking ? (
                      <motion.button
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.97 }}
                        onClick={() => onBookingClick(booking)}
                        className={`relative z-10 w-full text-left p-2 rounded-xl border cursor-pointer transition-colors shadow-sm ${getStatusStyle(booking.status)} ${isNow ? 'ring-2 ring-gold/50' : ''}`}
                      >
                        <div className="flex items-start justify-between mb-0.5">
                          <div className="flex items-center gap-1.5">
                            <div className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${getStatusDot(booking.status)}`} />
                            <p className="text-text-primary font-medium text-xs truncate">{booking.customerName}</p>
                          </div>
                          <span className="text-text-secondary text-[9px]">{booking.duration}min</span>
                        </div>
                        <p className="text-[10px] truncate pl-3 opacity-75">{booking.service}</p>
                        <p className="text-[10px] pl-3 opacity-50 mt-0.5">{booking.time} · {booking.staffName}</p>
                        {(() => {
                          const opState = getOperationalStateDisplay(booking);
                          return opState ? (
                            <p className={`text-[9px] pl-3 mt-0.5 ${opState.color}`}>{opState.text}</p>
                          ) : null;
                        })()}
                      </motion.button>
                    ) : (
                      <div 
                        className={`relative z-10 h-[44px] rounded-xl border border-dashed flex items-center justify-center group cursor-pointer transition-all ${isNow ? 'border-gold/30 bg-gold/5' : 'border-border-medium hover:border-gold/30 hover:bg-gold/5'}`}
                      >
                        <div className="w-6 h-6 rounded-full bg-gold/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                          <div className="w-2 h-0.5 bg-gold rounded-full" />
                          <div className="w-0.5 h-2 bg-gold rounded-full absolute" />
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>

    </div>
  );
}

// ─── Root export ──────────────────────────────────────────────────────────────
export default function TimelineView({
  bookings,
  staff,
  onBookingClick,
  currentUserRole,
  currentUserName,
}: TimelineViewProps) {
  const visibleStaff = currentUserRole === 'employee'
    ? staff.filter(s => s.name === currentUserName)
    : staff;

  return (
    <>
      {/* Mobile: one-column swipeable view */}
      <div className="lg:hidden">
        <SingleColumnTimeline
          staff={visibleStaff}
          bookings={bookings}
          onBookingClick={onBookingClick}
        />
      </div>

      {/* Desktop: full multi-column grid */}
      <div className="hidden lg:block">
        <MultiColumnTimeline
          staff={visibleStaff}
          bookings={bookings}
          onBookingClick={onBookingClick}
        />
      </div>
    </>
  );
}
