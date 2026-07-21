'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { X, Clock, Phone, DollarSign, MessageSquare, CheckCircle, RotateCcw, XCircle, Play, User, Scissors } from 'lucide-react';

interface BookingDetailsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  booking: {
    customerName: string;
    time: string;
    endTime: string;
    service: string;
    staffName: string;
    phone: string;
    status: string;
    price: number;
    notes?: string;
  };
  onCheckIn?: () => void;
  onStartService?: () => void;
  onCompleteService?: () => void;
  onReschedule?: () => void;
  onCancel?: () => void;
  currentUserRole?: string;
}

// Status config for cinematic header accent
const STATUS_STYLE: Record<string, { label: string; chip: string; dot: string }> = {
  confirmed:   { label: 'Confirmed',   chip: 'bg-blue-500/20 border-blue-500/30 text-blue-400',    dot: 'bg-blue-500'    },
  checked_in:  { label: 'Checked In',  chip: 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400', dot: 'bg-emerald-500' },
  in_service:  { label: 'In Service',  chip: 'bg-[#FFD700]/20 border-[#FFD700]/30 text-gold', dot: 'bg-[#FFD700]'   },
  completed:   { label: 'Completed',   chip: 'bg-zinc-700/40 border-zinc-600/30 text-zinc-400',    dot: 'bg-zinc-500'    },
  cancelled:   { label: 'Cancelled',   chip: 'bg-red-500/20 border-red-500/30 text-red-400',       dot: 'bg-red-500'     },
};

// Cinematic header photos — cycle by first letter of customer name
const HEADER_PHOTOS = [
  '/images/salon-station.jpg',
  '/images/salon-barber.jpg',
  '/images/salon-mirror.jpg',
  '/images/salon-styling.jpg',
  '/images/salon-hero.jpg',
];

function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

function getHeaderPhoto(name: string) {
  const code = name.charCodeAt(0) || 0;
  return HEADER_PHOTOS[code % HEADER_PHOTOS.length];
}

export default function BookingDetailsDrawer({
  isOpen,
  onClose,
  booking,
  onCheckIn,
  onStartService,
  onCompleteService,
  onReschedule,
  onCancel,
  currentUserRole,
}: BookingDetailsDrawerProps) {
  const canCheckIn       = currentUserRole === 'manager' || currentUserRole === 'receptionist';
  const canStartService  = currentUserRole === 'employee' || currentUserRole === 'manager';
  const canCompleteService = currentUserRole === 'employee' || currentUserRole === 'manager';
  const canReschedule    = currentUserRole === 'manager' || currentUserRole === 'receptionist' || currentUserRole === 'owner';
  const canCancel        = currentUserRole === 'manager' || currentUserRole === 'receptionist' || currentUserRole === 'owner';

  const statusStyle = STATUS_STYLE[booking.status] ?? STATUS_STYLE['confirmed'];
  const headerPhoto = getHeaderPhoto(booking.customerName);

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-overlay backdrop-blur-sm z-50"
          />

          {/* Drawer */}
          <motion.div
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', damping: 32, stiffness: 280 }}
            className="fixed right-0 top-0 h-full w-full max-w-md bg-surface border-l border-border-medium z-50 flex flex-col shadow-2xl"
          >
            {/* ── Cinematic Header ─────────────────────────── */}
            <div className="relative h-[220px] shrink-0 overflow-hidden">
              {/* Photo */}
              <img
                src={headerPhoto}
                alt=""
                className="absolute inset-0 w-full h-full object-cover"
                style={{ objectPosition: 'center 35%' }}
              />
              {/* Scrim */}
              <div className="absolute inset-0 bg-gradient-to-b from-black/20 via-black/30 to-[#111]" />

              {/* Close button */}
              <button
                onClick={onClose}
                className="absolute top-4 right-4 p-2.5 bg-surface backdrop-blur-md border border-border-light rounded-full text-text-primary/80 hover:text-text-primary hover:bg-card transition-all z-10"
              >
                <X className="w-4 h-4" />
              </button>

              {/* Status chip */}
              <div className="absolute top-4 left-4">
                <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border ${statusStyle.chip}`}>
                  <span className={`w-1.5 h-1.5 rounded-full ${statusStyle.dot}`} />
                  {statusStyle.label}
                </span>
              </div>

              {/* Customer info in scrim */}
              <div className="absolute bottom-0 left-0 right-0 px-5 pb-5">
                {/* Avatar */}
                <div className="relative w-fit mb-3">
                  <div className="w-[60px] h-[60px] rounded-xl bg-gradient-to-br from-[#FFD700]/30 to-[#C9A227]/20 border border-[#FFD700]/30 flex items-center justify-center text-xl font-bold text-gold shadow-xl">
                    {getInitials(booking.customerName)}
                  </div>
                </div>
                <h2 className="text-xl font-bold text-text-primary tracking-tight drop-shadow-md mb-0.5">
                  {booking.customerName}
                </h2>
                <p className="text-text-secondary text-sm flex items-center gap-2">
                  <Clock className="w-3.5 h-3.5" />
                  {booking.time} – {booking.endTime}
                </p>
              </div>
            </div>

            {/* ── Scrollable body ──────────────────────────── */}
            <div className="flex-1 overflow-y-auto">
              <div className="p-5 space-y-4">

                {/* Contact */}
                <div className="flex items-center gap-3 p-3 rounded-xl bg-card border border-border-medium">
                  <Phone className="w-4 h-4 text-gold shrink-0" />
                  <span className="text-text-primary text-sm">{booking.phone}</span>
                  <button className="ml-auto p-1.5 bg-card hover:bg-white/10 rounded-lg transition-colors">
                    <MessageSquare className="w-3.5 h-3.5 text-text-secondary" />
                  </button>
                </div>

                {/* Service + Price */}
                <div className="bg-card border border-border-medium rounded-xl p-4">
                  <p className="text-text-secondary text-xs uppercase tracking-wider mb-2 font-semibold">Service</p>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Scissors className="w-4 h-4 text-gold" />
                      <p className="text-text-primary font-semibold">{booking.service}</p>
                    </div>
                    <p className="text-gold font-bold text-sm">UGX {booking.price.toLocaleString()}</p>
                  </div>
                </div>

                {/* Staff */}
                <div className="bg-card border border-border-medium rounded-xl p-4">
                  <p className="text-text-secondary text-xs uppercase tracking-wider mb-2 font-semibold">Assigned Staff</p>
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-xs font-bold text-text-primary">
                      {getInitials(booking.staffName)}
                    </div>
                    <p className="text-text-primary font-medium">{booking.staffName}</p>
                  </div>
                </div>

                {/* Notes */}
                {booking.notes && (
                  <div className="bg-card border border-border-medium rounded-xl p-4">
                    <p className="text-text-secondary text-xs uppercase tracking-wider mb-2 font-semibold flex items-center gap-2">
                      <MessageSquare className="w-3.5 h-3.5" /> Notes
                    </p>
                    <p className="text-text-primary/80 text-sm leading-relaxed">{booking.notes}</p>
                  </div>
                )}

                {/* ── Actions ─────────────────────────────── */}
                <div className="space-y-2.5 pt-1">
                  <p className="text-text-secondary text-xs uppercase tracking-wider font-semibold">Actions</p>

                  {canCheckIn && booking.status === 'confirmed' && (
                    <button
                      onClick={onCheckIn}
                      className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 text-text-primary font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-emerald-900/30"
                    >
                      <CheckCircle className="w-4 h-4" /> Check In
                    </button>
                  )}

                  {canStartService && booking.status === 'checked_in' && (
                    <button
                      onClick={onStartService}
                      className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/20"
                    >
                      <Play className="w-4 h-4" /> Start Service
                    </button>
                  )}

                  {canCompleteService && booking.status === 'in_service' && (
                    <button
                      onClick={onCompleteService}
                      className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 text-text-primary font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-emerald-900/30"
                    >
                      <CheckCircle className="w-4 h-4" /> Complete Service
                    </button>
                  )}

                  {canReschedule && (
                    <button
                      onClick={onReschedule}
                      className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-surface border border-border-light text-text-primary font-medium hover:bg-card transition-colors"
                    >
                      <RotateCcw className="w-4 h-4" /> Reschedule
                    </button>
                  )}

                  {canCancel && (
                    <button
                      onClick={onCancel}
                      className="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-red-500/8 border border-red-500/20 text-red-400 font-medium hover:bg-red-500/15 transition-colors"
                    >
                      <XCircle className="w-4 h-4" /> Cancel Booking
                    </button>
                  )}
                </div>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
