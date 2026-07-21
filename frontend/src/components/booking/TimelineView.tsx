'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface Booking {
  id: string;
  customerName: string;
  service: string;
  staffName: string;
  time: string;
  endTime: string;
  duration: number;
  status: string;
  price: number;
  phone: string;
  notes?: string;
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
    const bookingHour = parseInt(b.time.split(':')[0]);
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
            return (
              <div key={hour} className="flex items-stretch gap-3 min-h-[56px]">
                {/* Time label */}
                <div className="w-12 flex-shrink-0 flex items-start pt-1">
                  <span className="text-text-muted text-xs font-medium">
                    {hour > 12 ? `${hour - 12}PM` : hour === 12 ? '12PM' : `${hour}AM`}
                  </span>
                </div>

                {/* Divider + slot */}
                <div className="flex-1 relative">
                  <div className="absolute top-3 left-0 right-0 border-t border-border-light/40" />
                  {booking ? (
                    <motion.button
                      whileTap={{ scale: 0.97 }}
                      onClick={() => onBookingClick(booking)}
                      className={`relative z-10 w-full text-left p-3 rounded-xl border cursor-pointer transition-all shadow-sm ${getStatusStyle(booking.status)}`}
                    >
                      <div className="flex items-center gap-2 mb-1">
                        <div className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${getStatusDot(booking.status)}`} />
                        <span className="text-text-primary font-semibold text-sm truncate">{booking.customerName}</span>
                      </div>
                      <p className="text-xs truncate pl-3.5 opacity-80">{booking.service}</p>
                      <p className="text-xs pl-3.5 opacity-60 mt-0.5">{booking.time} · UGX {booking.price.toLocaleString()}</p>
                    </motion.button>
                  ) : (
                    <div className="relative z-10 w-full h-[48px] rounded-xl border border-dashed border-border-medium hover:border-gold/15 transition-colors" />
                  )}
                </div>
              </div>
            );
          })}
        </motion.div>
      </AnimatePresence>

      {/* Legend */}
      <div className="flex flex-wrap items-center justify-center gap-3 px-4 py-3 border-t border-border-light">
        {[
          { label: 'Confirmed', dot: 'bg-emerald-400' },
          { label: 'In Service', dot: 'bg-gold' },
          { label: 'Checked In', dot: 'bg-blue-400' },
          { label: 'Cancelled', dot: 'bg-red-400' },
        ].map(item => (
          <div key={item.label} className="flex items-center gap-1.5">
            <div className={`w-2 h-2 rounded-full ${item.dot}`} />
            <span className="text-text-muted text-[10px]">{item.label}</span>
          </div>
        ))}
      </div>
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
        {hours.map(hour => (
          <div key={hour} className="flex items-stretch min-h-[52px]">
            <div className="w-16 flex-shrink-0 flex items-start pt-1">
              <span className="text-text-muted text-xs font-medium">
                {hour > 12 ? `${hour - 12}PM` : hour === 12 ? '12PM' : `${hour}AM`}
              </span>
            </div>
            {staff.map(s => {
              const booking = getBookingForSlot(bookings, s.name, hour);
              return (
                <div key={s.id} className="flex-1 px-1 relative">
                  <div className="absolute top-3 left-1 right-1 border-t border-border-light/40" />
                  {booking ? (
                    <motion.button
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.97 }}
                      onClick={() => onBookingClick(booking)}
                      className={`relative z-10 w-full text-left p-2 rounded-xl border cursor-pointer transition-colors shadow-sm ${getStatusStyle(booking.status)}`}
                    >
                      <div className="flex items-center gap-1.5 mb-0.5">
                        <div className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${getStatusDot(booking.status)}`} />
                        <p className="text-text-primary font-medium text-xs truncate">{booking.customerName}</p>
                      </div>
                      <p className="text-[10px] truncate pl-3 opacity-75">{booking.service}</p>
                      <p className="text-[10px] pl-3 opacity-50 mt-0.5">{booking.time}</p>
                    </motion.button>
                  ) : (
                    <div className="relative z-10 h-[44px] rounded-xl border border-dashed border-border-medium hover:border-gold/15 transition-colors" />
                  )}
                </div>
              );
            })}
          </div>
        ))}
      </div>

      {/* Legend */}
      <div className="flex flex-wrap items-center justify-center gap-4 mt-4 pt-4 border-t border-border-light">
        {[
          { label: 'Confirmed', dot: 'bg-emerald-400' },
          { label: 'Checked In', dot: 'bg-blue-400' },
          { label: 'In Service', dot: 'bg-gold' },
          { label: 'Completed', dot: 'bg-text-secondary' },
          { label: 'Cancelled', dot: 'bg-red-400' },
        ].map(item => (
          <div key={item.label} className="flex items-center gap-2">
            <div className={`w-2.5 h-2.5 rounded-full ${item.dot}`} />
            <span className="text-text-muted text-xs">{item.label}</span>
          </div>
        ))}
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
