'use client';

import { useEffect, useState, useMemo, use, useRef } from 'react';
import { useRouter } from 'next/navigation';
import {
  Users, Search, Plus, Calendar, Clock, Star,
  MessageSquare, MoreVertical, X, CalendarDays,
  CheckCircle2, AlertCircle, TrendingUp, Scissors,
  ChevronRight, Zap, Mail
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { Avatar } from '@/components/ui/Avatar';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { CopyLinkModal } from '@/components/ui/CopyLinkModal';
import { useQuery } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { salonRoutes } from '@/lib/routes';

// ─── Types ────────────────────────────────────────────────────────────────────

type StaffStatus = 'available' | 'busy' | 'break' | 'off' | 'leave' | 'training' | 'lunch';

interface ScheduleSlot {
  time: string;
  client: string;
  service: string;
  status: 'completed' | 'current' | 'upcoming';
}

interface StaffMember {
  id: string;
  name: string;
  role: string;
  photo?: string | null;
  photo_url?: string | null;
  email?: string;
  phone?: string;
  active: boolean;
  status: StaffStatus;
  rating: number;
  employeeSince: string;
  skills: string[];
  // Today
  todayAppointments: number;
  totalAppointments: number;  // capacity for the day
  currentClient?: string;     // name of person currently being served
  busyUntil?: string;
  nextClientTime?: string;
  nextClientName?: string;
  minutesFree?: number;       // "free in X mins"
  // Schedule
  schedule: ScheduleSlot[];
  // Performance
  performance: {
    weeklyAppointments: number;
    weeklyRevenue: number;
    rebookingRate: number;
  };
  workingHours: Record<string, string>;
  upcomingLeave?: string;
  notes?: string;
}

// ─── Status Config ─────────────────────────────────────────────────────────────

const STATUS_CONFIG: Record<StaffStatus, {
  emoji: string;
  label: string;
  color: string;         // dot / accent color (Tailwind)
  textColor: string;
  bgColor: string;
  borderColor: string;
  barColor: string;      // top bar gradient
}> = {
  available:  { emoji: '🟢', label: 'Available',   color: 'bg-emerald-500', textColor: 'text-emerald-400', bgColor: 'bg-emerald-500/10',  borderColor: 'border-emerald-500/25', barColor: 'from-emerald-500 to-emerald-400' },
  busy:       { emoji: '🟠', label: 'With Client',  color: 'bg-orange-500',  textColor: 'text-orange-400',  bgColor: 'bg-orange-500/10',   borderColor: 'border-orange-500/25',  barColor: 'from-orange-500 to-amber-400'  },
  break:      { emoji: '🔵', label: 'On Break',     color: 'bg-blue-500',    textColor: 'text-blue-400',    bgColor: 'bg-blue-500/10',     borderColor: 'border-blue-500/25',    barColor: 'from-blue-500 to-blue-400'    },
  lunch:      { emoji: '🟡', label: 'Lunch',        color: 'bg-yellow-500',  textColor: 'text-yellow-400',  bgColor: 'bg-yellow-500/10',   borderColor: 'border-yellow-500/25', barColor: 'from-yellow-500 to-yellow-400'},
  training:   { emoji: '🟣', label: 'Training',     color: 'bg-purple-500',  textColor: 'text-purple-400',  bgColor: 'bg-purple-500/10',   borderColor: 'border-purple-500/25', barColor: 'from-purple-500 to-purple-400'},
  off:        { emoji: '⚪', label: 'Off Today',    color: 'bg-zinc-500',    textColor: 'text-zinc-400',    bgColor: 'bg-zinc-500/10',     borderColor: 'border-zinc-500/25',    barColor: 'from-zinc-600 to-zinc-500'    },
  leave:      { emoji: '🌴', label: 'On Leave',     color: 'bg-amber-500',   textColor: 'text-amber-400',   bgColor: 'bg-amber-500/10',    borderColor: 'border-amber-500/25',   barColor: 'from-amber-500 to-amber-400'  },
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

// Avatar helpers are now inside the Avatar component — no local helpers needed.

// ─── Schedule Progress Bar ────────────────────────────────────────────────────

function ScheduleBar({ done, total }: { done: number; total: number }) {
  if (total === 0) return null;
  const pct = Math.min(100, Math.round((done / total) * 100));
  const filled = Math.round((pct / 100) * 8);

  return (
    <div>
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-[11px] text-text-secondary font-medium uppercase tracking-wider">Today's Progress</span>
        <span className="text-[11px] text-text-primary/60 font-medium">{done} / {total}</span>
      </div>
      <div className="flex gap-0.5">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className={`h-1.5 flex-1 rounded-full transition-all ${
              i < filled ? 'bg-[#FFD700]' : 'bg-white/10'
            }`}
          />
        ))}
      </div>
    </div>
  );
}

// ─── Star Rating ──────────────────────────────────────────────────────────────

function StarRating({ rating }: { rating: number }) {
  const full = Math.floor(rating);
  return (
    <div className="flex items-center gap-0.5">
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          className={`w-3 h-3 ${i < full ? 'text-gold fill-[#FFD700]' : 'text-text-primary/20 fill-white/10'}`}
        />
      ))}
      <span className="text-text-primary/70 text-[11px] ml-1 font-medium">{rating}</span>
    </div>
  );
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({ member }: { member: StaffMember }) {
  const cfg = STATUS_CONFIG[member.status];
  return (
    <div className="space-y-1">
      <div className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${cfg.bgColor} ${cfg.textColor} border ${cfg.borderColor}`}>
        <span className="text-[10px]">{cfg.emoji}</span>
        {cfg.label}
      </div>
      {/* Context line */}
      {member.status === 'busy' && member.currentClient && (
        <p className="text-[11px] text-text-primary/50 pl-1">
          with <span className="text-text-primary/80 font-medium">{member.currentClient}</span>
          {member.busyUntil && <> · until {member.busyUntil}</>}
        </p>
      )}
      {member.status === 'available' && member.minutesFree && (
        <p className="text-[11px] text-emerald-400/70 pl-1 flex items-center gap-1">
          <Zap className="w-2.5 h-2.5" /> Free for {member.minutesFree} min
        </p>
      )}
      {member.status === 'leave' && member.upcomingLeave && (
        <p className="text-[11px] text-amber-400/70 pl-1">{member.upcomingLeave}</p>
      )}
    </div>
  );
}

// ─── Staff Card ───────────────────────────────────────────────────────────────

function StaffCard({ member, onClick }: { member: StaffMember; onClick: () => void }) {
  const cfg = STATUS_CONFIG[member.status];
  // Derive a hex color from the status dot class for the Avatar component
  const statusHex = {
    available: '#10b981',
    busy:      '#f97316',
    break:     '#3b82f6',
    lunch:     '#eab308',
    training:  '#a855f7',
    off:       '#71717a',
    leave:     '#f59e0b',
  }[member.status];

  return (
    <motion.div
      layoutId={`card-${member.id}`}
      onClick={onClick}
      whileHover={{ y: -4, boxShadow: '0 20px 60px rgba(0,0,0,0.4)' }}
      transition={{ duration: 0.2 }}
      className="relative bg-card border border-border-medium rounded-2xl overflow-hidden cursor-pointer group"
    >
      {/* Status top bar */}
      <div className={`h-[3px] w-full bg-gradient-to-r ${cfg.barColor} opacity-80`} />

      <div className="p-5 flex flex-col gap-4">
        {/* Avatar + name */}
        <div className="flex flex-col items-center text-center gap-3 pt-2">
          {/* Avatar — photo or gradient initials */}
          <Avatar
            name={member.name}
            src={member.photo_url}
            seed={member.id}
            size="xl"
            shape="circle"
            statusColor={statusHex}
          />

          {/* Name + role */}
          <div>
            <h3 className="text-text-primary font-semibold text-base leading-tight">{member.name}</h3>
            <p className="text-text-secondary text-xs mt-0.5">{member.role}</p>
          </div>

          {/* Stars */}
          <StarRating rating={member.rating} />
        </div>

        {/* Divider */}
        <div className="h-px bg-card" />

        {/* Status */}
        <StatusBadge member={member} />

        {/* Context info */}
        {(member.status === 'available' || member.status === 'busy') && (
          <div className="bg-surface border border-border-light rounded-xl p-3 space-y-2">
            {member.status === 'busy' && member.nextClientTime && (
              <div className="flex items-center justify-between text-[12px]">
                <span className="text-text-secondary">Next</span>
                <span className="text-text-primary font-medium">
                  {member.nextClientTime}
                  {member.nextClientName && <span className="text-text-primary/50"> · {member.nextClientName}</span>}
                </span>
              </div>
            )}
            {member.status === 'available' && member.nextClientTime && (
              <div className="flex items-center justify-between text-[12px]">
                <span className="text-text-secondary">Next booking</span>
                <span className="text-text-primary font-medium">{member.nextClientTime}</span>
              </div>
            )}
          </div>
        )}

        {/* Schedule progress */}
        {member.totalAppointments > 0 && (
          <ScheduleBar done={member.todayAppointments} total={member.totalAppointments} />
        )}

        {/* Off / leave state */}
        {(member.status === 'off' || member.status === 'leave') && (
          <div className={`${cfg.bgColor} border ${cfg.borderColor} rounded-xl p-3 text-center`}>
            <p className={`${cfg.textColor} text-xs`}>
              {member.status === 'off' ? 'Not scheduled today' : member.upcomingLeave || 'On Leave'}
            </p>
          </div>
        )}
      </div>

      {/* Hover glow overlay */}
      <div className="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none ring-1 ring-inset ring-[#FFD700]/20" />
    </motion.div>
  );
}

// ─── Drawer: Today's Timeline ─────────────────────────────────────────────────

function Timeline({ schedule }: { schedule: ScheduleSlot[] }) {
  if (schedule.length === 0) {
    return (
      <div className="bg-surface border border-border-medium rounded-xl p-6 flex flex-col items-center text-center gap-3">
        <div className="w-10 h-10 rounded-full bg-card border border-border-light flex items-center justify-center">
          <Scissors className="w-4 h-4 text-text-primary/20" />
        </div>
        <div>
          <p className="text-text-primary/60 text-sm font-medium">No appointments today</p>
          <p className="text-text-secondary text-xs mt-1">Their schedule is clear for the day</p>
        </div>
      </div>
    );
  }

  return (
    <div className="relative pl-4 border-l border-border-light space-y-4">
      {schedule.map((slot, i) => {
        const isCurrent = slot.status === 'current';
        const isDone    = slot.status === 'completed';
        return (
          <div key={i} className="relative">
            {/* Timeline dot */}
            <div className={`absolute -left-[21px] top-2 w-2.5 h-2.5 rounded-full border-2 border-[#121212] ${
              isDone    ? 'bg-zinc-600' :
              isCurrent ? 'bg-[#FFD700] shadow-[0_0_10px_rgba(255,215,0,0.5)]' :
                          'bg-white/25'
            }`} />
            <div className={`rounded-xl p-3 border transition-colors ${
              isCurrent
                ? 'bg-[#FFD700]/8 border-[#FFD700]/20'
                : isDone
                ? 'bg-black/10 border-white/5 opacity-60'
                : 'bg-card border-border-medium'
            }`}>
              <div className="flex items-center justify-between mb-0.5">
                <span className="text-xs text-text-secondary font-mono">{slot.time}</span>
                {isCurrent && (
                  <span className="text-[10px] px-1.5 py-0.5 bg-[#FFD700]/15 text-gold border border-[#FFD700]/25 rounded font-semibold">NOW</span>
                )}
                {isDone && (
                  <span className="text-[10px] text-zinc-600 font-medium">✓ Done</span>
                )}
              </div>
              <p className="text-text-primary text-sm font-medium">{slot.client}</p>
              <p className="text-text-secondary text-xs">{slot.service}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ─── Drawer ───────────────────────────────────────────────────────────────────

// Cinematic photo pool — one per person, keyed by avatar gradient index
const HEADER_PHOTOS = [
  '/images/salon-barber.jpg',
  '/images/salon-mirror.jpg',
  '/images/salon-station.jpg',
  '/images/salon-styling.jpg',
  '/images/salon-hero.jpg',
  '/images/salon-luxury.png',
];

function ProfileDrawer({ member, onClose }: { member: StaffMember; onClose: () => void }) {
  const cfg = STATUS_CONFIG[member.status];
  const headerPhoto = HEADER_PHOTOS[parseInt(member.id, 10) % HEADER_PHOTOS.length];
  const statusHex = {
    available: '#10b981', busy: '#f97316', break: '#3b82f6',
    lunch: '#eab308', training: '#a855f7', off: '#71717a', leave: '#f59e0b',
  }[member.status];
  const [scrollY, setScrollY] = useState(0);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  const handleScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const scrollTop = e.currentTarget.scrollTop;
    setScrollY(scrollTop);
  };

  const headerHeight = Math.max(60, 280 - scrollY);
  const headerOpacity = Math.max(0.3, 1 - scrollY / 200);

  return (
    <>
      {/* Backdrop */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        onClick={onClose}
        className="fixed inset-0 bg-overlay backdrop-blur-sm z-40"
      />

      {/* Drawer panel */}
      <motion.div
        initial={{ x: '100%' }}
        animate={{ x: 0 }}
        exit={{ x: '100%' }}
        transition={{ type: 'spring', damping: 32, stiffness: 280 }}
        className="fixed top-0 right-0 h-full w-full max-w-[480px] bg-surface border-l border-border-medium z-50 shadow-2xl flex flex-col"
      >
        {/* ── Cinematic Header ──────────────────────────────────── */}
        <motion.div
          style={{ height: headerHeight, opacity: headerOpacity }}
          className="relative shrink-0 overflow-hidden"
        >
          {/* Full-bleed photo */}
          <img
            src={headerPhoto}
            alt=""
            className="absolute inset-0 w-full h-full object-cover"
            style={{ objectPosition: 'center 25%' }}
          />

          {/* Scrim: dark gradient fading to drawer bg */}
          <div className="absolute inset-0 bg-gradient-to-b from-background/25 via-background/30 to-surface" />

          {/* Status colour tint — subtle mood accent */}
          <div
            className={`absolute inset-0 opacity-[0.08] bg-gradient-to-br ${cfg.barColor}`}
            style={{ mixBlendMode: 'screen' }}
          />

          {/* Close — top-right glass pill */}
          <button
            onClick={onClose}
            className="absolute top-4 right-4 p-2.5 bg-surface backdrop-blur-md border border-border-light rounded-full text-text-primary/80 hover:text-text-primary hover:bg-card transition-all z-10"
          >
            <X className="w-4 h-4" />
          </button>

          {/* Status chip — top-left glassmorphic */}
          <div className="absolute top-4 left-4">
            <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md bg-surface border border-border-light ${cfg.textColor}`}>
              <span className="text-[10px]">{cfg.emoji}</span>
              {cfg.label}
            </div>
          </div>

          {/* Avatar + name in the scrim area */}
          <div className="absolute bottom-0 left-0 right-0 px-6 pb-5">
            {/* Avatar */}
            <div className="relative w-fit mb-3">
              <Avatar
                name={member.name}
                src={member.photo_url}
                seed={member.id}
                size="2xl"
                shape="rounded"
                statusColor={statusHex}
              />
            </div>

            <h2 className="text-[22px] font-bold text-text-primary tracking-tight mb-0.5 drop-shadow-md">
              {member.name}
            </h2>
            <p className="text-gold text-sm font-semibold mb-2">{member.role}</p>
            <div className="flex items-center gap-3">
              <StarRating rating={member.rating} />
              <span className="text-text-primary/50 text-xs flex items-center gap-1">
                <Calendar className="w-3 h-3" /> Since {member.employeeSince}
              </span>
            </div>
          </div>
        </motion.div>

        {/* ── Quick Actions ──────────────────────────────────────── */}
        <div className="px-5 py-4 flex gap-2 shrink-0">
          <button className="flex-1 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/10">
            Assign Booking
          </button>
          <button className="px-4 py-2.5 bg-surface text-text-primary rounded-xl text-sm font-medium hover:bg-card transition-colors border border-border-medium">
            Edit
          </button>
          <button className="p-2.5 bg-surface text-text-secondary rounded-xl hover:bg-card hover:text-text-primary transition-colors border border-border-medium">
            <MessageSquare className="w-5 h-5" />
          </button>
          <button className="p-2.5 bg-surface text-text-secondary rounded-xl hover:bg-card hover:text-text-primary transition-colors border border-border-medium">
            <MoreVertical className="w-5 h-5" />
          </button>
        </div>

        {/* ── Live Status Strip ─────────────────────────────────── */}
        <div className={`mx-5 mb-2 ${cfg.bgColor} border ${cfg.borderColor} rounded-xl p-3.5 flex items-center gap-2.5 shrink-0`}>
          <span className="text-xl">{cfg.emoji}</span>
          <div className="flex-1 min-w-0">
            <p className={`text-sm font-semibold ${cfg.textColor}`}>{cfg.label}</p>
            {member.status === 'busy' && member.currentClient && (
              <p className="text-text-primary/60 text-xs mt-0.5 truncate">
                Serving <span className="text-text-primary font-medium">{member.currentClient}</span>
                {member.busyUntil && <> · Ends {member.busyUntil}</>}
              </p>
            )}
            {member.status === 'available' && member.nextClientTime && (
              <p className="text-text-primary/60 text-xs mt-0.5">
                Next: <span className="text-text-primary font-medium">{member.nextClientName}</span> at {member.nextClientTime}
              </p>
            )}
            {member.status === 'leave' && (
              <p className="text-text-primary/60 text-xs mt-0.5">{member.upcomingLeave}</p>
            )}
          </div>
        </div>

        <div className="h-px bg-border-light mx-5 shrink-0 mt-3" />

        {/* ── Scrollable Body ───────────────────────────────────── */}
        <div
          ref={scrollContainerRef}
          onScroll={handleScroll}
          className="flex-1 overflow-y-auto"
        >
          <div className="p-5 space-y-8">

          {/* Today's schedule */}
          <div>
            <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2">
              <Clock className="w-3.5 h-3.5 text-gold" /> Today's Schedule
            </h3>
            <Timeline schedule={member.schedule} />
          </div>

          {/* Workload */}
          {member.totalAppointments > 0 && (
            <div>
              <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
                <TrendingUp className="w-3.5 h-3.5 text-gold" /> Workload
              </h3>
              <div className="bg-card border border-border-medium rounded-xl p-4">
                <ScheduleBar done={member.todayAppointments} total={member.totalAppointments} />
                <p className="text-text-secondary text-xs mt-3">
                  {member.totalAppointments - member.todayAppointments} slots remaining today
                </p>
              </div>
            </div>
          )}

          {/* This week performance */}
          <div>
            <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
              <TrendingUp className="w-3.5 h-3.5 text-gold" /> This Week
            </h3>
            <div className="grid grid-cols-2 gap-3">
              {[
                { label: 'Appointments', value: member.performance.weeklyAppointments },
                { label: 'Revenue', value: `UGX ${(member.performance.weeklyRevenue / 1000).toFixed(0)}k` },
                { label: 'Avg. Rating', value: `${member.rating} ★` },
                { label: 'Rebooking', value: `${member.performance.rebookingRate.toFixed(0)}%` },
              ].map(({ label, value }) => (
                <div key={label} className="bg-card border border-border-medium rounded-xl p-4">
                  <p className="text-text-secondary text-xs mb-1">{label}</p>
                  <p className="text-text-primary text-xl font-bold">{value}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Skills */}
          <div>
            <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
              <Scissors className="w-3.5 h-3.5 text-gold" /> Skills & Services
            </h3>
            <div className="flex flex-wrap gap-2">
              {Array.isArray(member.skills) && member.skills.length > 0 ? (
                member.skills.map((skill, i) => (
                  <span key={i} className="px-3 py-1.5 bg-[#FFD700]/10 border border-[#FFD700]/20 text-gold text-xs rounded-full flex items-center gap-1.5">
                    <CheckCircle2 className="w-3 h-3" /> {skill}
                  </span>
                ))
              ) : (
                <span className="text-text-secondary text-sm">No skills listed</span>
              )}
            </div>
          </div>

          {/* Working hours */}
          <div>
            <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
              <CalendarDays className="w-3.5 h-3.5 text-gold" /> Working Hours
            </h3>
            <div className="bg-card border border-border-medium rounded-xl overflow-hidden divide-y divide-white/5">
              {Object.entries(member.workingHours).map(([day, hours]) => (
                <div key={day} className="flex justify-between items-center px-4 py-2.5 text-sm">
                  <span className="text-text-secondary w-10">{day}</span>
                  <span className={hours === 'Off' ? 'text-zinc-500' : 'text-text-primary'}>{hours}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Notes & leave */}
          {(member.notes || member.upcomingLeave) && (
            <div>
              <h3 className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
                <AlertCircle className="w-3.5 h-3.5 text-gold" /> Notes & Leave
              </h3>
              <div className="space-y-3">
                {member.upcomingLeave && (
                  <div className="bg-amber-500/10 border border-amber-500/20 p-4 rounded-xl">
                    <p className="text-amber-400 text-xs font-semibold uppercase tracking-wider mb-1">Upcoming Leave</p>
                    <p className="text-amber-100 text-sm">{member.upcomingLeave}</p>
                  </div>
                )}
                {member.notes && (
                  <div className="bg-card border border-border-medium p-4 rounded-xl">
                    <p className="text-text-secondary text-xs font-semibold uppercase tracking-wider mb-2">Manager Notes</p>
                    <p className="text-text-primary/80 text-sm whitespace-pre-line leading-relaxed">{member.notes}</p>
                  </div>
                )}
              </div>
            </div>
          )}
          </div>
        </div>
      </motion.div>
    </>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function SalonStaffPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const { salonId, user, activeSalon, salonSlug } = useRole();
  const routes = salonRoutes(salonSlug);
  const [staff, setStaff]               = useState<StaffMember[]>([]);
  const [searchTerm, setSearchTerm]     = useState('');
  const [activeFilter, setActiveFilter] = useState('All');
  const [mounted, setMounted]           = useState(false);
  const [selectedStaff, setSelectedStaff] = useState<StaffMember | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [addError, setAddError] = useState('');
  const [staffForm, setStaffForm] = useState({ name: '', role: '', email: '', phone: '', photo_media_id: '' });
  const [invitationLink, setInvitationLink] = useState('');
  const [isCopyLinkModalOpen, setIsCopyLinkModalOpen] = useState(false);
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const [inviteRole, setInviteRole] = useState<'staff' | 'specialist' | 'manager' | 'receptionist'>('staff');

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/staff`);
      return;
    }

    if (!salonId) {
      console.warn('[SalonStaffPage] User has no salon assignment');
      return;
    }

    // Verify the salon slug matches the user's active salon
    if (activeSalon && activeSalon.slug !== slug) {
      console.error('[SalonStaffPage] Access denied: user salon does not match URL salon');
      router.push(routes.dashboard);
    }
  }, [salonId, user, router, slug, activeSalon]);

  const handlePhotoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploadingPhoto(true);
    try {
      const result = await apiClient.uploadMedia(file, { directory: 'staff-photos' });
      setStaffForm({ ...staffForm, photo_media_id: result.id });
    } catch (error) {
      console.error('Failed to upload photo:', error);
      setAddError('Failed to upload photo');
    } finally {
      setIsUploadingPhoto(false);
    }
  };

  const handleAddStaff = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setAddError('');
    try {
      const newMember = await apiClient.createStaffMember({
        name: staffForm.name,
        role: staffForm.role,
        email: staffForm.email || undefined,
        phone: staffForm.phone || undefined,
        salon_id: salonId,
        active: true,
        photo_media_id: staffForm.photo_media_id || undefined,
      });
      // Optimistically add to the list with defaults
      const mapped: StaffMember = {
        id: String(newMember.id),
        photo: newMember.photo_url ?? null,
        name: newMember.name,
        role: newMember.role || staffForm.role || 'Stylist',
        email: newMember.email,
        phone: newMember.phone,
        active: true,
        status: 'available',
        rating: 5.0,
        employeeSince: new Date().getFullYear().toString(),
        skills: [],
        todayAppointments: 0,
        totalAppointments: 0,
        schedule: [],
        performance: { weeklyAppointments: 0, weeklyRevenue: 0, rebookingRate: 0 },
        workingHours: { Mon: '9 AM–6 PM', Tue: '9 AM–6 PM', Wed: '9 AM–6 PM', Thu: '9 AM–6 PM', Fri: '9 AM–6 PM', Sat: '9 AM–6 PM', Sun: 'Off' },
      };
      setStaff(prev => [mapped, ...prev]);
      setIsAddModalOpen(false);
      setStaffForm({ name: '', role: '', email: '', phone: '', photo_media_id: '' });

      // Auto-generate invitation link if email provided
      if (staffForm.email) {
        try {
          const result = await apiClient.createInvitation({
            role: 'staff',
            email: staffForm.email,
            target_id: String(newMember.id),
          });
          setInvitationLink(result.join_url);
          setIsCopyLinkModalOpen(true);
        } catch {
          // Invitation generation failed silently — staff was still created
        }
      }
    } catch (err: any) {
      setAddError(err?.response?.data?.message || 'Failed to add staff member. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const { data, isLoading } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: !!salonId,
  });

  useEffect(() => {
    setMounted(true);
  }, []);

  // Fetch bookings to calculate real staff performance data
  const { data: bookings = [] } = useQuery({
    queryKey: ['bookings', salonId],
    queryFn: () => apiClient.getBookings({ salon_id: salonId }),
    enabled: !!salonId,
  });

  useEffect(() => {
    if (data) {
      // Map API staff objects to the required UI shape with real calculated data
      const loadedStaff = data.map((s: any) => {
        // Calculate real appointment counts from bookings
        const staffBookings = bookings.filter((b: any) => b.staff_id === s.id);
        const todayBookings = staffBookings.filter((b: any) => {
          if (!b.date) return false;
          const bookingDate = new Date(b.date).toDateString();
          const today = new Date().toDateString();
          return bookingDate === today;
        });
        
        // Calculate weekly performance
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        const weeklyBookings = staffBookings.filter((b: any) => {
          if (!b.date) return false;
          return new Date(b.date) >= weekAgo;
        });
        const weeklyRevenue = weeklyBookings.reduce((sum: number, b: any) => sum + (b.service?.price || 0), 0);
        
        // Calculate rebooking rate (simplified - customers with multiple bookings)
        const customerBookings: Record<string, number> = {};
        staffBookings.forEach((b: any) => {
          const customerId = b.customer_id;
          if (customerId) {
            customerBookings[customerId] = (customerBookings[customerId] || 0) + 1;
          }
        });
        const returningCustomers = Object.values(customerBookings).filter(count => count > 1).length;
        const rebookingRate = staffBookings.length > 0 ? (returningCustomers / staffBookings.length) * 100 : 0;

        return {
          id: String(s.id),
          photo: s.photo_url ?? s.photo ?? null,
          name: s.name,
          role: s.role || 'Stylist',
          email: s.email,
          phone: s.phone,
          active: s.active,
          status: s.status || 'available',
          rating: s.rating || 4.5,
          employeeSince: s.created_at ? new Date(s.created_at).getFullYear().toString() : '2025',
          skills: s.specializations || ['Haircut'],
          todayAppointments: todayBookings.length,
          totalAppointments: todayBookings.length,
          schedule: todayBookings.map((b: any) => ({
            time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
            client: b.customer?.name || 'Unknown',
            service: b.service?.name || 'Service',
            status: b.status === 'completed' ? 'completed' : b.status === 'in_service' ? 'current' : 'upcoming',
          })),
          performance: { 
            weeklyAppointments: weeklyBookings.length, 
            weeklyRevenue, 
            rebookingRate 
          },
          workingHours: s.working_hours || { Mon: '9 AM–6 PM', Tue: '9 AM–6 PM', Wed: '9 AM–6 PM', Thu: '9 AM–6 PM', Fri: '9 AM–6 PM', Sat: '9 AM–6 PM', Sun: 'Off' },
          notes: s.notes,
        };
      });
      setStaff(loadedStaff);
    }
  }, [data, bookings]);

  // ── Derived counts ──────────────────────────────────────────────────────────
  const counts = useMemo(() => ({
    available: staff.filter(s => s.status === 'available').length,
    busy:      staff.filter(s => s.status === 'busy').length,
    leave:     staff.filter(s => s.status === 'leave').length,
    off:       staff.filter(s => s.status === 'off').length,
  }), [staff]);

  // ── Headline message ────────────────────────────────────────────────────────
  const now = new Date();
  const hour = now.getHours();
  const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';

  const busyMember = staff.find(s => s.status === 'busy' && s.minutesFree);
  const headlineDetail = busyMember
    ? `${busyMember.name} finishes their current client in ${busyMember.minutesFree} min.`
    : counts.available > 0
    ? `${counts.available} team members available.`
    : 'All team members are busy or off today.';

  // ── Filtered staff ───────────────────────────────────────────────────────────
  const filteredStaff = useMemo(() => {
    let result = staff;
    if (searchTerm) {
      const lower = searchTerm.toLowerCase();
      result = result.filter(s => 
        s.name.toLowerCase().includes(lower) ||
        s.role.toLowerCase().includes(lower) ||
        s.skills.some(skill => skill.toLowerCase().includes(lower))
      );
    }
    if (activeFilter !== 'All') {
      result = result.filter(s => s.status === activeFilter);
    }
    return result;
  }, [staff, searchTerm, activeFilter]);

  if (!mounted) return null;

  return (
    <DashboardLayout>
      <div className="max-w-7xl mx-auto p-6 font-sans space-y-6">
        
        {/* No salon assignment state */}
        {!salonId && user && (
          <div className="flex flex-col items-center justify-center py-24 text-center">
            <div className="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center mb-4">
              <Users className="w-8 h-8 text-gold" />
            </div>
            <h2 className="text-xl font-semibold text-text-primary mb-2">No Salon Access</h2>
            <p className="text-text-secondary mb-6 max-w-md">
              You don't have access to this salon's staff management.
            </p>
            <button
              onClick={() => router.push('/dashboard')}
              className="px-6 py-2.5 bg-gold text-black rounded-xl font-medium hover:bg-gold/90 transition-colors"
            >
              Dashboard
            </button>
          </div>
        )}

        {/* Staff management UI - only shown when salonId exists */}
        {salonId && (
          <>
            {/* Header */}
            <div className="flex items-center justify-between">
              <div>
                <p className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-1">Team</p>
                <h1 className="text-2xl font-bold text-text-primary tracking-tight">
                  {greeting}, {headlineDetail}
                </h1>
              </div>
              <div className="flex items-center gap-2">
                <div className="relative hidden sm:block">
                  <select
                    value={inviteRole}
                    onChange={(e) => setInviteRole(e.target.value as any)}
                    className="appearance-none bg-card border border-border-light text-text-primary rounded-xl text-sm font-medium px-4 py-2.5 pr-8 focus:outline-none focus:border-[rgba(255,215,0,0.4)] cursor-pointer"
                  >
                    <option value="staff">Staff</option>
                    <option value="specialist">Specialist</option>
                    <option value="manager">Manager</option>
                    <option value="receptionist">Receptionist</option>
                  </select>
                  <ChevronRight className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary pointer-events-none rotate-90" />
                </div>
                <button
                  onClick={async () => {
                    try {
                      const result = await apiClient.createInvitation({
                        role: inviteRole,
                      });
                      setInvitationLink(result.join_url);
                      setIsCopyLinkModalOpen(true);
                    } catch (error) {
                      console.error('Failed to create invitation:', error);
                    }
                  }}
                  className="flex items-center gap-2 px-4 py-2.5 bg-card border border-border-light text-text-primary rounded-xl text-sm font-medium hover:bg-card hover:border-[rgba(255,215,0,0.4)] transition-all"
                  aria-label="Create invite link"
                >
                  <Mail className="w-4 h-4" /> <span className="hidden sm:inline">Create Invite Link</span>
                </button>
                <button
                  onClick={() => setIsAddModalOpen(true)}
                  className="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/10"
                  aria-label="Add staff"
                >
                  <Plus className="w-4 h-4" /> <span className="hidden sm:inline">Add Staff</span>
                </button>
              </div>
            </div>

            {/* Status chips */}
            <div className="flex items-center gap-2">
              {[
                { label: 'All', count: staff.length },
                { label: 'Available', count: counts.available, color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
                { label: 'Busy', count: counts.busy, color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' },
                { label: 'Leave', count: counts.leave, color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
                { label: 'Off', count: counts.off, color: 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20' },
              ].map(({ label, count, color }) => (
                <button
                  key={label}
                  onClick={() => setActiveFilter(label)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-all ${
                    activeFilter === label
                      ? 'bg-[#FFD700] text-black border-[#FFD700]'
                      : 'bg-card text-text-secondary border-border-light hover:border-border-medium'
                  } ${color}`}
                >
                  {label} ({count})
                </button>
              ))}
            </div>

            {/* Search */}
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
              <input
                type="text"
                placeholder="Search staff by name, role, or skills..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 bg-card border border-border-light rounded-xl text-text-primary placeholder:text-text-secondary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
              />
            </div>

            {/* Staff grid */}
            {isLoading ? (
              <div className="text-center py-12 text-text-secondary">Loading staff...</div>
            ) : filteredStaff.length === 0 ? (
              <div className="text-center py-12 text-text-secondary">
                {searchTerm || activeFilter !== 'All' ? 'No staff match your filters.' : 'No staff members yet.'}
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                {filteredStaff.map((member) => (
                  <StaffCard
                    key={member.id}
                    member={member}
                    onClick={() => setSelectedStaff(member)}
                  />
                ))}
              </div>
            )}

            {/* Add Staff Modal */}
            <AnimatePresence>
              {isAddModalOpen && (
                <motion.div
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  className="fixed inset-0 bg-overlay backdrop-blur-sm z-50 flex items-center justify-center p-4"
                >
                  <motion.div
                    initial={{ scale: 0.95, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    exit={{ scale: 0.95, opacity: 0 }}
                    className="bg-surface border border-border-medium rounded-2xl p-6 w-full max-w-md"
                  >
                    <div className="flex items-center justify-between mb-6">
                      <h2 className="text-lg font-bold text-text-primary">Add Staff Member</h2>
                      <button onClick={() => setIsAddModalOpen(false)} className="text-text-secondary hover:text-text-primary">
                        <X className="w-5 h-5" />
                      </button>
                    </div>
                    <form onSubmit={handleAddStaff} className="space-y-4">
                      {addError && (
                        <div className="bg-red-500/10 border border-red-500/20 text-red-400 text-sm px-3 py-2 rounded-lg">
                          {addError}
                        </div>
                      )}
                      {/* Photo Upload */}
                      <div>
                        <label className="block text-xs font-medium text-text-secondary mb-1.5">Photo</label>
                        <div className="flex items-center gap-3">
                          <div className="w-16 h-16 rounded-xl bg-card border border-border-light flex items-center justify-center overflow-hidden">
                            {staffForm.photo_media_id ? (
                              <img
                                src={`${process.env.NEXT_PUBLIC_API_URL || '/backend/api'}/v1/media/${staffForm.photo_media_id}`}
                                alt="Preview"
                                className="w-full h-full object-cover"
                              />
                            ) : (
                              <span className="text-text-secondary text-xs">No photo</span>
                            )}
                          </div>
                          <div className="flex-1">
                            <input
                              type="file"
                              accept="image/*"
                              onChange={handlePhotoUpload}
                              disabled={isUploadingPhoto}
                              className="hidden"
                              id="photo-upload"
                            />
                            <label
                              htmlFor="photo-upload"
                              className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium cursor-pointer transition-all ${
                                isUploadingPhoto
                                  ? 'bg-card border border-border-light text-text-secondary cursor-not-allowed'
                                  : 'bg-card border border-border-light text-text-primary hover:border-[rgba(255,215,0,0.4)]'
                              }`}
                            >
                              {isUploadingPhoto ? 'Uploading...' : 'Choose Photo'}
                            </label>
                            {staffForm.photo_media_id && (
                              <button
                                type="button"
                                onClick={() => setStaffForm({ ...staffForm, photo_media_id: '' })}
                                className="ml-2 text-xs text-text-secondary hover:text-text-primary"
                              >
                                Remove
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-text-secondary mb-1.5">Name *</label>
                        <input
                          type="text"
                          required
                          value={staffForm.name}
                          onChange={(e) => setStaffForm({ ...staffForm, name: e.target.value })}
                          className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)]"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-text-secondary mb-1.5">Role *</label>
                        <input
                          type="text"
                          required
                          value={staffForm.role}
                          onChange={(e) => setStaffForm({ ...staffForm, role: e.target.value })}
                          placeholder="e.g., Stylist, Barber"
                          className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)]"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-text-secondary mb-1.5">Email</label>
                        <input
                          type="email"
                          value={staffForm.email}
                          onChange={(e) => setStaffForm({ ...staffForm, email: e.target.value })}
                          className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)]"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-text-secondary mb-1.5">Phone</label>
                        <input
                          type="tel"
                          value={staffForm.phone}
                          onChange={(e) => setStaffForm({ ...staffForm, phone: e.target.value })}
                          className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)]"
                        />
                      </div>
                      <div className="flex gap-3 pt-2">
                        <button
                          type="button"
                          onClick={() => setIsAddModalOpen(false)}
                          className="flex-1 px-4 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm font-medium hover:bg-card transition-colors"
                        >
                          Cancel
                        </button>
                        <button
                          type="submit"
                          disabled={isSubmitting}
                          className="flex-1 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity disabled:opacity-50"
                        >
                          {isSubmitting ? 'Adding...' : 'Add Staff'}
                        </button>
                      </div>
                    </form>
                  </motion.div>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Profile Drawer */}
            <AnimatePresence>
              {selectedStaff && (
                <ProfileDrawer
                  member={selectedStaff}
                  onClose={() => setSelectedStaff(null)}
                />
              )}
            </AnimatePresence>

            {/* Copy Link Modal */}
            <CopyLinkModal
              isOpen={isCopyLinkModalOpen}
              onClose={() => setIsCopyLinkModalOpen(false)}
              title="Share Invitation Link"
              description="Copy this link to share with the staff member"
              link={invitationLink}
            />
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
