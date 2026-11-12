'use client';

import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Activity, Clock, Check, AlertTriangle, User, UserCheck,
  Play, CheckCircle, Loader2, AlertCircle, Timer,
  ArrowRight,
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import DashboardLayout from '@/components/DashboardLayout';
import {
  FloorSpecialistCard,
  type SpecialistFloorState,
} from '@/components/specialist/BreakWidget';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SnapshotCustomer  { id: string | null; name: string; is_guest: boolean }
interface SnapshotService   { id: string | null; name: string; duration: number | null }
interface SnapshotSpecialist { id: string; name: string; floor_state: SpecialistFloorState }

interface SnapshotBooking {
  id: string;
  status: string;
  time: string;
  arrived_at: string | null;
  waiting_since: string | null;
  wait_minutes: number | null;
  queue_position: number | null;
  /** The schedulable BookingService.id — used for start/complete calls */
  booking_service_id: string | null;
  customer: SnapshotCustomer;
  service: SnapshotService;
  specialist: SnapshotSpecialist | null;
  // only present in in_progress items (merged by formatLiveSession)
  session?: {
    id: string;
    started_at: string;
    elapsed_min: number | null;
    expected_end: string | null;
  } | null;
}

interface FloorSnapshot {
  date: string;
  snapshot: {
    confirmed:   SnapshotBooking[];
    arrived:     SnapshotBooking[];
    waiting:     SnapshotBooking[];
    in_progress: SnapshotBooking[];
  };
  counts: {
    confirmed: number;
    arrived:   number;
    waiting:   number;
    in_progress: number;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Live elapsed timer hook — derived from a real ISO timestamp, never a counter
// ─────────────────────────────────────────────────────────────────────────────

function useElapsedTimer(startedAtIso: string | null | undefined): string {
  const [display, setDisplay] = useState('');
  useEffect(() => {
    if (!startedAtIso) { setDisplay(''); return; }
    const tick = () => {
      const elapsed = Date.now() - new Date(startedAtIso).getTime();
      if (elapsed < 0) { setDisplay('0:00'); return; }
      const totalSec = Math.floor(elapsed / 1000);
      const h = Math.floor(totalSec / 3600);
      const m = Math.floor((totalSec % 3600) / 60);
      const s = totalSec % 60;
      const pad = (n: number) => String(n).padStart(2, '0');
      setDisplay(h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [startedAtIso]);
  return display;
}

// ─────────────────────────────────────────────────────────────────────────────
// Queue item — a single booking row with contextual actions
// ─────────────────────────────────────────────────────────────────────────────

interface QueueItemProps {
  booking: SnapshotBooking;
  canOperateFloor: boolean;
  onArrive:         (b: SnapshotBooking) => void;
  onWait:           (b: SnapshotBooking) => void;
  onStartService:   (b: SnapshotBooking) => void;
  onCompleteService:(b: SnapshotBooking) => void;
  pendingAction: string | null; // bookingId currently pending
}

function QueueItem({
  booking, canOperateFloor, onArrive, onWait, onStartService, onCompleteService, pendingAction,
}: QueueItemProps) {
  const isPending = pendingAction === booking.id;
  const specialist = booking.specialist;
  const sessionStartedAt = booking.session?.started_at ?? null;
  const elapsedTimer = useElapsedTimer(sessionStartedAt);

  // Derive specialist availability for "Start Service" gate
  const specialistIsAvailable = specialist?.floor_state?.status === 'AVAILABLE';

  // Status badge config
  const statusConfig: Record<string, { label: string; color: string; dot: string }> = {
    confirmed:   { label: 'Confirmed',   color: 'text-blue-400',    dot: 'bg-blue-400' },
    arrived:     { label: 'Arrived',     color: 'text-gold',        dot: 'bg-gold' },
    waiting:     { label: 'Waiting',     color: 'text-amber-400',   dot: 'bg-amber-400 animate-pulse' },
    in_progress: { label: 'In Service',  color: 'text-emerald-400', dot: 'bg-emerald-400' },
  };
  const cfg = statusConfig[booking.status] ?? { label: booking.status, color: 'text-text-muted', dot: 'bg-text-muted' };

  return (
    <motion.div
      layout
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, height: 0, marginBottom: 0 }}
      transition={{ type: 'spring', damping: 22, stiffness: 260 }}
      className={`
        rounded-2xl border p-4 transition-colors
        ${booking.status === 'in_progress'
          ? 'bg-emerald-500/5 border-emerald-500/20'
          : 'bg-surface border-border-light hover:border-gold/20'}
      `}
    >
      {/* Main row */}
      <div className="flex items-start gap-3">
        {/* Avatar */}
        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold/60 to-amber-600/60 flex items-center justify-center text-white font-bold text-sm shrink-0">
          {(booking.customer.name || 'G').charAt(0).toUpperCase()}
        </div>

        {/* Details */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <p className="font-semibold text-text-primary text-sm">
              {booking.customer.name || 'Walk-in Guest'}
            </p>
            {booking.customer.is_guest && (
              <span className="text-[10px] px-1.5 py-0.5 rounded-md bg-gold/10 text-gold border border-gold/20 font-medium">
                Walk-in
              </span>
            )}
          </div>
          <p className="text-xs text-text-muted mt-0.5 truncate">
            {booking.service?.name ?? 'Service'}
            {' · '}
            {specialist ? (
              <span className="text-text-secondary">{specialist.name}</span>
            ) : (
              <span className="text-red-400/80 italic">Unassigned</span>
            )}
          </p>
          {/* Waiting duration */}
          {booking.status === 'waiting' && booking.wait_minutes != null && booking.wait_minutes > 0 && (
            <p className="text-xs text-amber-400 mt-1 flex items-center gap-1">
              <Clock className="w-3 h-3" />
              Waiting {booking.wait_minutes}m
            </p>
          )}
          {/* Live service timer — anchored to backend started_at */}
          {booking.status === 'in_progress' && sessionStartedAt && (
            <p className="text-xs text-emerald-400 mt-1 flex items-center gap-1 font-mono tabular-nums">
              <Timer className="w-3 h-3" />
              {elapsedTimer}
            </p>
          )}
        </div>

        {/* Status badge */}
        <div className="shrink-0 flex items-center gap-1.5">
          <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />
          <span className={`text-xs font-medium ${cfg.color}`}>{cfg.label}</span>
        </div>
      </div>

      {/* Floor operator actions — contextual per booking state */}
      {canOperateFloor && (
        <div className="mt-3 flex gap-2 flex-wrap">
          {/* confirmed → arrived */}
          {booking.status === 'confirmed' && (
            <ActionButton
              label="Arrived"
              icon={<UserCheck className="w-3.5 h-3.5" />}
              color="blue"
              pending={isPending}
              onClick={() => onArrive(booking)}
            />
          )}

          {/* arrived → waiting */}
          {booking.status === 'arrived' && (
            <ActionButton
              label="Add to Queue"
              icon={<ArrowRight className="w-3.5 h-3.5" />}
              color="amber"
              pending={isPending}
              onClick={() => onWait(booking)}
            />
          )}

          {/* waiting → start service (only if specialist is AVAILABLE) */}
          {booking.status === 'waiting' && (
            <ActionButton
              label="Start Service"
              icon={<Play className="w-3.5 h-3.5" />}
              color="gold"
              pending={isPending}
              disabled={!specialistIsAvailable}
              disabledReason={
                !specialist
                  ? 'No specialist assigned'
                  : specialist.floor_state?.status === 'BUSY'
                  ? 'Specialist is serving another client'
                  : specialist.floor_state?.status === 'ON_BREAK'
                  ? 'Specialist is on break'
                  : undefined
              }
              onClick={() => onStartService(booking)}
            />
          )}

          {/* in_progress → complete */}
          {booking.status === 'in_progress' && (
            <ActionButton
              label="Complete Service"
              icon={<CheckCircle className="w-3.5 h-3.5" />}
              color="emerald"
              pending={isPending}
              onClick={() => onCompleteService(booking)}
            />
          )}
        </div>
      )}
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Reusable action button
// ─────────────────────────────────────────────────────────────────────────────

interface ActionButtonProps {
  label: string;
  icon: React.ReactNode;
  color: 'blue' | 'amber' | 'gold' | 'emerald';
  pending?: boolean;
  disabled?: boolean;
  disabledReason?: string;
  onClick: () => void;
}

function ActionButton({ label, icon, color, pending, disabled, disabledReason, onClick }: ActionButtonProps) {
  const colorMap = {
    blue:    'bg-blue-500/15 hover:bg-blue-500/25 text-blue-400 border-blue-400/20',
    amber:   'bg-amber-500/15 hover:bg-amber-500/25 text-amber-400 border-amber-400/20',
    gold:    'bg-gold/15 hover:bg-gold/25 text-gold border-gold/20',
    emerald: 'bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 border-emerald-400/20',
  };

  return (
    <div className="relative group/btn">
      <button
        onClick={onClick}
        disabled={disabled || pending}
        className={`
          flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium
          transition-colors disabled:opacity-40 disabled:cursor-not-allowed
          ${colorMap[color]}
        `}
      >
        {pending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : icon}
        {pending ? 'Working…' : label}
      </button>
      {/* Tooltip for disabled reason */}
      {disabled && disabledReason && (
        <div className="absolute bottom-full left-0 mb-1.5 hidden group-hover/btn:block z-10">
          <div className="bg-card border border-border-light rounded-xl px-3 py-2 text-xs text-text-secondary whitespace-nowrap shadow-xl">
            {disabledReason}
          </div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Section label
// ─────────────────────────────────────────────────────────────────────────────

function SectionLabel({ label, count, color = 'text-text-secondary' }: { label: string; count: number; color?: string }) {
  return (
    <div className={`flex items-center gap-2 text-[10px] tracking-widest font-bold uppercase ${color}`}>
      <span>{label}</span>
      {count > 0 && (
        <span className="px-1.5 py-0.5 rounded-md bg-current/10 font-mono">{count}</span>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Pulse Page
// ─────────────────────────────────────────────────────────────────────────────

export default function PulsePage() {
  const { providerId, locationId, hasCapability } = useRole();
  const queryClient = useQueryClient();
  const [now, setNow] = useState(new Date());
  const [stationStates, setStationStates] = useState<Record<string, SpecialistFloorState>>({});
  const [pendingAction, setPendingAction] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const canOperateFloor = hasCapability('operate_floor');

  // Clock — display only, not used as source of truth for any timer
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(id);
  }, []);

  // Legacy pulse data (capacity bar, next appointment, notifications)
  const { data: pulseData, isLoading: pulseLoading } = useQuery({
    queryKey: ['pulse', providerId],
    queryFn:  () => apiClient.getPulseData({ provider_id: providerId || undefined }),
    enabled:  !!providerId,
    refetchInterval: 30000,
  });

  // Floor snapshot — operational queue + in_progress sessions
  const { data: snapshotData, isLoading: snapshotLoading } = useQuery<FloorSnapshot>({
    queryKey: ['floor-snapshot', locationId],
    queryFn:  () => apiClient.getFloorSnapshot(),
    enabled:  !!locationId,
    refetchInterval: 15000, // faster poll for operational accuracy
  });

  const invalidateAll = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['pulse'] });
    queryClient.invalidateQueries({ queryKey: ['floor-snapshot'] });
  }, [queryClient]);

  // ── Mutations — all delegate to FloorController → BookingService/SessionService

  const arriveMutation = useMutation({
    mutationFn: (booking: SnapshotBooking) => apiClient.bookingArrive(booking.id),
    onMutate:   (b) => { setPendingAction(b.id); setActionError(null); },
    onSettled:  () => { setPendingAction(null); invalidateAll(); },
    onError:    (e: any) => setActionError(e?.response?.data?.message || e?.message || 'Failed to mark arrived.'),
  });

  const waitMutation = useMutation({
    mutationFn: (booking: SnapshotBooking) => apiClient.bookingWait(booking.id),
    onMutate:   (b) => { setPendingAction(b.id); setActionError(null); },
    onSettled:  () => { setPendingAction(null); invalidateAll(); },
    onError:    (e: any) => setActionError(e?.response?.data?.message || e?.message || 'Failed to add to queue.'),
  });

  const startServiceMutation = useMutation({
    mutationFn: (booking: SnapshotBooking) => {
      // For pre-start (waiting) bookings, use booking_service_id from the queue payload.
      // For already-in-progress items (re-entry), use session.id.
      const bsId = booking.booking_service_id ?? booking.session?.id;
      if (!bsId) {
        throw new Error('No booking service available. Please refresh and try again.');
      }
      return apiClient.floorStartSession(booking.id, bsId);
    },
    onMutate:   (b) => { setPendingAction(b.id); setActionError(null); },
    onSettled:  () => { setPendingAction(null); invalidateAll(); },
    onError:    (e: any) => setActionError(e?.response?.data?.message || e?.message || 'Failed to start service.'),
  });

  const completeServiceMutation = useMutation({
    mutationFn: (booking: SnapshotBooking) => {
      const sessionId = booking.session?.id;
      if (!sessionId) throw new Error('No active session found.');
      return apiClient.floorCompleteSession(booking.id, sessionId);
    },
    onMutate:   (b) => { setPendingAction(b.id); setActionError(null); },
    onSettled:  () => { setPendingAction(null); invalidateAll(); },
    onError:    (e: any) => setActionError(e?.response?.data?.message || e?.message || 'Failed to complete service.'),
  });

  if (pulseLoading || snapshotLoading) {
    return (
      <DashboardLayout>
        <div className="flex h-full items-center justify-center">
          <div className="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" />
        </div>
      </DashboardLayout>
    );
  }

  const { capacity, stations, nextAppointment, appointmentsRemaining, timeline, notifications, totalBookingsToday } = pulseData ?? {
    capacity: { label: '—', percentage: 0, busy: 0, total: 0 },
    stations: [],
    nextAppointment: null,
    appointmentsRemaining: 0,
    timeline: [],
    notifications: [],
    totalBookingsToday: 0,
  };

  const snapshot = snapshotData?.snapshot ?? {
    confirmed: [], arrived: [], waiting: [], in_progress: [],
  };

  // Build combined queue: confirmed + arrived + waiting + in_progress (ordered: waiting first)
  const queueItems: SnapshotBooking[] = [
    ...snapshot.in_progress,
    ...snapshot.waiting,
    ...snapshot.arrived,
    ...snapshot.confirmed,
  ];

  const hasQueueActivity = queueItems.length > 0;

  const formattedTime = now.toLocaleTimeString('en-US', {
    hour:   '2-digit',
    minute: '2-digit',
    hour12: true,
  });

  return (
    <DashboardLayout>
      <div className="max-w-[1200px] mx-auto pb-20 px-4">

        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
          <div>
            <h1 className="text-4xl md:text-5xl font-bold tracking-tight text-[var(--color-text-primary)] flex items-center gap-4 mb-2">
              {formattedTime}
              <div className="w-3 h-3 rounded-full bg-emerald-500 animate-pulse border-2 border-emerald-500/20 shadow-[0_0_10px_rgba(16,185,129,0.5)]" />
            </h1>
            <div className="flex items-center gap-3">
              <span className="text-gold font-bold tracking-[0.2em] uppercase text-sm">Today</span>
              <span className="w-1.5 h-1.5 rounded-full bg-[var(--color-text-muted)]/20" />
              <span className="text-[var(--color-text-secondary)]">{totalBookingsToday} Appointments</span>
            </div>
          </div>

          {/* Capacity widget */}
          <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl p-5 md:w-80 shadow-md">
            <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase mb-3">
              Current Atmosphere
            </p>
            <div className="flex justify-between items-end mb-2">
              <p className="text-2xl font-bold text-[var(--color-text-primary)]">{capacity.label}</p>
              <p className="text-gold font-semibold">{capacity.percentage}%</p>
            </div>
            <div className="flex gap-1 h-2 mb-3">
              {Array.from({ length: 10 }).map((_, i) => (
                <div
                  key={i}
                  className={`flex-1 rounded-sm ${i < Math.round(capacity.percentage / 10) ? 'bg-gold' : 'bg-[var(--color-text-muted)]/20'}`}
                />
              ))}
            </div>
            <p className="text-xs text-[var(--color-text-secondary)]">{capacity.busy} of {capacity.total} Chairs Active</p>
          </div>
        </div>

        {/* Global action error banner */}
        <AnimatePresence>
          {actionError && (
            <motion.div
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              className="flex items-center gap-3 mb-6 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-sm"
            >
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span className="flex-1">{actionError}</span>
              <button onClick={() => setActionError(null)} className="text-red-400/60 hover:text-red-400">✕</button>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Main grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">

          {/* LEFT: Operational Queue */}
          <div className="lg:col-span-5 flex flex-col gap-6">

            {/* Operational Queue Panel */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 shadow-sm">
              <div className="flex items-center justify-between mb-6">
                <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase flex items-center gap-2">
                  <Activity className="w-3 h-3" />
                  Floor Queue
                </p>
                {canOperateFloor && (
                  <span className="text-[10px] px-2 py-1 rounded-lg bg-gold/10 text-gold border border-gold/20 font-medium">
                    Floor Operator
                  </span>
                )}
              </div>

              {!hasQueueActivity ? (
                <div className="text-center py-10">
                  <User className="w-8 h-8 text-text-muted mx-auto mb-3 opacity-40" />
                  <p className="text-text-secondary text-sm">No active clients on the floor.</p>
                </div>
              ) : (
                <div className="flex flex-col gap-3">
                  {/* In Progress */}
                  {snapshot.in_progress.length > 0 && (
                    <div className="flex flex-col gap-2">
                      <SectionLabel label="In Service" count={snapshot.in_progress.length} color="text-emerald-400" />
                      <AnimatePresence>
                        {snapshot.in_progress.map(b => (
                          <QueueItem
                            key={b.id}
                            booking={b}
                            canOperateFloor={canOperateFloor}
                            pendingAction={pendingAction}
                            onArrive={arriveMutation.mutate}
                            onWait={waitMutation.mutate}
                            onStartService={startServiceMutation.mutate}
                            onCompleteService={completeServiceMutation.mutate}
                          />
                        ))}
                      </AnimatePresence>
                    </div>
                  )}

                  {/* Waiting */}
                  {snapshot.waiting.length > 0 && (
                    <div className="flex flex-col gap-2 mt-2">
                      <SectionLabel label="Waiting" count={snapshot.waiting.length} color="text-amber-400" />
                      <AnimatePresence>
                        {snapshot.waiting.map(b => (
                          <QueueItem
                            key={b.id}
                            booking={b}
                            canOperateFloor={canOperateFloor}
                            pendingAction={pendingAction}
                            onArrive={arriveMutation.mutate}
                            onWait={waitMutation.mutate}
                            onStartService={startServiceMutation.mutate}
                            onCompleteService={completeServiceMutation.mutate}
                          />
                        ))}
                      </AnimatePresence>
                    </div>
                  )}

                  {/* Arrived */}
                  {snapshot.arrived.length > 0 && (
                    <div className="flex flex-col gap-2 mt-2">
                      <SectionLabel label="Arrived" count={snapshot.arrived.length} color="text-gold" />
                      <AnimatePresence>
                        {snapshot.arrived.map(b => (
                          <QueueItem
                            key={b.id}
                            booking={b}
                            canOperateFloor={canOperateFloor}
                            pendingAction={pendingAction}
                            onArrive={arriveMutation.mutate}
                            onWait={waitMutation.mutate}
                            onStartService={startServiceMutation.mutate}
                            onCompleteService={completeServiceMutation.mutate}
                          />
                        ))}
                      </AnimatePresence>
                    </div>
                  )}

                  {/* Confirmed (today, not yet arrived) */}
                  {snapshot.confirmed.length > 0 && (
                    <div className="flex flex-col gap-2 mt-2">
                      <SectionLabel label="Confirmed" count={snapshot.confirmed.length} color="text-blue-400" />
                      <AnimatePresence>
                        {snapshot.confirmed.map(b => (
                          <QueueItem
                            key={b.id}
                            booking={b}
                            canOperateFloor={canOperateFloor}
                            pendingAction={pendingAction}
                            onArrive={arriveMutation.mutate}
                            onWait={waitMutation.mutate}
                            onStartService={startServiceMutation.mutate}
                            onCompleteService={completeServiceMutation.mutate}
                          />
                        ))}
                      </AnimatePresence>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Next Up widget */}
            <div className="bg-gradient-to-b from-[var(--color-card)] to-[var(--color-surface)] border border-[var(--color-border-medium)] rounded-3xl p-6 shadow-lg relative overflow-hidden group">
              <div className="absolute inset-0 bg-gold/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700" />
              <div className="relative z-10">
                <p className="text-gold text-[10px] tracking-[0.25em] font-bold uppercase mb-6 flex items-center gap-2">
                  <Activity className="w-3 h-3" /> Next Up
                </p>
                {nextAppointment ? (
                  <>
                    <h2 className="text-3xl font-bold text-[var(--color-text-primary)] mb-1">{nextAppointment.time}</h2>
                    <p className="text-xl font-semibold text-[var(--color-text-primary)] mb-1">{nextAppointment.customer_name}</p>
                    <p className="text-[var(--color-text-secondary)] text-sm mb-6">
                      {nextAppointment.service_name} · {nextAppointment.staff_name}
                    </p>
                    <div className="space-y-2">
                      <div className="flex justify-between text-xs font-medium">
                        <span className="text-[var(--color-text-secondary)]">Arrival</span>
                        <span className="text-gold">in {nextAppointment.minutes_until} mins</span>
                      </div>
                      <div className="w-full bg-[var(--color-border-light)] rounded-full h-1 overflow-hidden">
                        <motion.div
                          className="h-full bg-gradient-to-r from-gold to-[#FF8C00]"
                          initial={{ width: 0 }}
                          animate={{ width: `${Math.max(10, 100 - (nextAppointment.minutes_until / 60) * 100)}%` }}
                          transition={{ duration: 1 }}
                        />
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="py-8 text-center">
                    <p className="text-[var(--color-text-secondary)]">No upcoming appointments</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* RIGHT: Stations + Activity */}
          <div className="lg:col-span-7 flex flex-col gap-8">

            {/* Stations grid */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 md:p-8 shadow-sm">
              <div className="flex justify-between items-center mb-8">
                <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase">
                  Active Stations
                </p>
                <div className="flex gap-4">
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.5)]" />
                    <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">Busy</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-orange-400 animate-pulse" />
                    <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">On Break</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]" />
                    <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">Available</span>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {stations.map((station: any, i: number) => {
                  const derivedState: SpecialistFloorState =
                    stationStates[station.specialist_id] ??
                    (station.floor_state ?? (
                      station.status === 'Busy'
                        ? { status: 'BUSY', expected_end_at: null }
                        : { status: 'AVAILABLE', expected_end_at: null }
                    ));

                  // Live session timer from the snapshot for BUSY specialists
                  const inProgressSession = snapshot.in_progress.find(
                    b => b.specialist?.id === station.specialist_id
                  );
                  const sessionStartedAt = inProgressSession?.session?.started_at ?? null;

                  return (
                    <FloorSpecialistCardWithTimer
                      key={station.specialist_id ?? i}
                      specialistId={station.specialist_id}
                      specialistName={station.staff_name}
                      providerId={providerId ?? ''}
                      locationId={locationId ?? undefined}
                      floorState={derivedState}
                      currentCustomer={station.customer_name}
                      canManageBreak={canOperateFloor}
                      sessionStartedAt={sessionStartedAt}
                      onStateChange={(s) =>
                        setStationStates(prev => ({ ...prev, [station.specialist_id]: s }))
                      }
                    />
                  );
                })}
              </div>
            </div>

            {/* Live Activity */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 md:p-8 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase">
                  Live Activity
                </p>
              </div>
              <div className="space-y-4">
                <AnimatePresence>
                  {notifications.map((note: any) => {
                    let Icon = Check;
                    let color = 'text-emerald-500';
                    let bg = 'bg-emerald-500/10';
                    let border = 'border-emerald-500/20';
                    if (note.type === 'warning') { Icon = AlertTriangle; color = 'text-amber-500'; bg = 'bg-amber-500/10'; border = 'border-amber-500/20'; }
                    else if (note.type === 'info') { Icon = UserCheck; color = 'text-blue-500'; bg = 'bg-blue-500/10'; border = 'border-blue-500/20'; }
                    return (
                      <motion.div
                        key={note.id}
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        className={`flex items-center gap-4 p-4 rounded-2xl border ${bg} ${border}`}
                      >
                        <div className="p-2 rounded-xl bg-[var(--color-surface)] shadow-sm shrink-0">
                          <Icon className={`w-4 h-4 ${color}`} />
                        </div>
                        <p className="text-sm text-[var(--color-text-primary)] font-medium flex-1">{note.message}</p>
                        <span className="text-xs text-[var(--color-text-secondary)] whitespace-nowrap">{note.time}</span>
                      </motion.div>
                    );
                  })}
                </AnimatePresence>
              </div>
            </div>

          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// FloorSpecialistCardWithTimer — wraps FloorSpecialistCard with a live timer
// in the BUSY subtitle slot, derived from the actual session started_at
// ─────────────────────────────────────────────────────────────────────────────

interface FloorSpecialistCardWithTimerProps {
  specialistId: string;
  specialistName: string;
  specialistPhoto?: string;
  providerId: string;
  locationId?: string;
  floorState: SpecialistFloorState;
  currentCustomer?: string;
  canManageBreak?: boolean;
  sessionStartedAt: string | null;
  onStateChange?: (state: SpecialistFloorState) => void;
}

function FloorSpecialistCardWithTimer({
  sessionStartedAt,
  ...cardProps
}: FloorSpecialistCardWithTimerProps) {
  const elapsed = useElapsedTimer(
    cardProps.floorState.status === 'BUSY' ? sessionStartedAt : null
  );

  const enhancedCustomer = cardProps.floorState.status === 'BUSY' && elapsed
    ? `${cardProps.currentCustomer ?? 'client'} · ${elapsed}`
    : cardProps.currentCustomer;

  return <FloorSpecialistCard {...cardProps} currentCustomer={enhancedCustomer} />;
}
