'use client';

import { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Coffee, Clock, Play, X, ChevronDown } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type FloorState = 'AVAILABLE' | 'BUSY' | 'ON_BREAK';

export interface SpecialistFloorState {
  status: FloorState;
  expected_end_at: string | null;
}

export interface ActiveBreak {
  id: string;
  started_at: string;
  expected_end_at: string;
  actual_end_at: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Countdown hook
// ─────────────────────────────────────────────────────────────────────────────

function useCountdown(targetIso: string | null): string {
  const [display, setDisplay] = useState('');

  useEffect(() => {
    if (!targetIso) { setDisplay(''); return; }
    const tick = () => {
      const diff = new Date(targetIso).getTime() - Date.now();
      if (diff <= 0) { setDisplay('00:00'); return; }
      const mins = Math.floor(diff / 60000);
      const secs = Math.floor((diff % 60000) / 1000);
      setDisplay(`${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [targetIso]);

  return display;
}

// ─────────────────────────────────────────────────────────────────────────────
// Duration presets
// ─────────────────────────────────────────────────────────────────────────────

const PRESETS = [5, 10, 15, 30] as const;

// ─────────────────────────────────────────────────────────────────────────────
// StatusPill — a small inline badge shown in specialist cards / headers
// ─────────────────────────────────────────────────────────────────────────────

export function SpecialistStatusPill({ state }: { state: SpecialistFloorState }) {
  const countdown = useCountdown(
    state.status === 'ON_BREAK' ? state.expected_end_at : null
  );

  const configs = {
    AVAILABLE: { label: 'Available', dot: 'bg-emerald-400', text: 'text-emerald-400', border: 'border-emerald-400/30', bg: 'bg-emerald-400/10' },
    BUSY:      { label: 'In Service', dot: 'bg-amber-400', text: 'text-amber-400', border: 'border-amber-400/30', bg: 'bg-amber-400/10' },
    ON_BREAK:  { label: countdown ? `Break · ${countdown}` : 'On Break', dot: 'bg-orange-400 animate-pulse', text: 'text-orange-400', border: 'border-orange-400/30', bg: 'bg-orange-400/10' },
  };

  const c = configs[state.status];

  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${c.bg} ${c.border} ${c.text}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${c.dot}`} />
      {c.label}
    </span>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// BreakWidget — the primary self-service widget for a specialist's own view
// Props: specialistId, providerId, locationId?, isOwnBreak (true for specialist)
// ─────────────────────────────────────────────────────────────────────────────

interface BreakWidgetProps {
  specialistId: string;
  providerId: string;
  locationId?: string;
  /** When true, uses specialist portal token. False = staff/manager auth. */
  isSpecialistPortal?: boolean;
  /** Initial floor state (from parent polling). Widget keeps its own refresh. */
  initialState?: SpecialistFloorState;
  /** Called after a successful start/end so parents can refresh their queue */
  onStateChange?: (state: SpecialistFloorState) => void;
  /** Compact layout for use inside small cards */
  compact?: boolean;
}

export function BreakWidget({
  specialistId,
  providerId,
  locationId,
  isSpecialistPortal = false,
  initialState,
  onStateChange,
  compact = false,
}: BreakWidgetProps) {
  const [floorState, setFloorState] = useState<SpecialistFloorState>(
    initialState ?? { status: 'AVAILABLE', expected_end_at: null }
  );
  const [showPicker, setShowPicker] = useState(false);
  const [customMinutes, setCustomMinutes] = useState('');
  const [selectedPreset, setSelectedPreset] = useState<number | 'custom'>(10);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const countdown = useCountdown(
    floorState.status === 'ON_BREAK' ? floorState.expected_end_at : null
  );

  // Sync if parent provides a fresh state
  useEffect(() => {
    if (initialState) setFloorState(initialState);
  }, [initialState]);

  const buildHeaders = useCallback(() => {
    const headers: Record<string, string> = {};
    const token = isSpecialistPortal
      ? localStorage.getItem('specialist_auth_token')
      : localStorage.getItem('auth_token');
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (locationId) headers['X-Location-Id'] = locationId;
    return headers;
  }, [isSpecialistPortal, locationId]);

  const startBreak = async () => {
    const minutes = selectedPreset === 'custom' ? parseInt(customMinutes) : selectedPreset;
    if (!minutes || minutes < 1 || minutes > 480) {
      setError('Please enter a valid duration (1–480 minutes).');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL || '/backend/api'}/v1/specialists/${specialistId}/breaks`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...buildHeaders() },
          body: JSON.stringify({ duration_minutes: minutes, location_id: locationId }),
        }
      );
      if (!res.ok) {
        const d = await res.json();
        throw new Error(Object.values(d.errors ?? {}).flat().join(' ') || d.message || 'Failed to start break');
      }
      const data = await res.json();
      const newState: SpecialistFloorState = {
        status: 'ON_BREAK',
        expected_end_at: data.break?.expected_end_at ?? null,
      };
      setFloorState(newState);
      onStateChange?.(newState);
      setShowPicker(false);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  const endBreak = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL || '/backend/api'}/v1/specialists/${specialistId}/breaks/end`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', ...buildHeaders() },
          body: JSON.stringify({ location_id: locationId }),
        }
      );
      if (!res.ok) {
        const d = await res.json();
        throw new Error(Object.values(d.errors ?? {}).flat().join(' ') || d.message || 'Failed to end break');
      }
      const newState: SpecialistFloorState = { status: 'AVAILABLE', expected_end_at: null };
      setFloorState(newState);
      onStateChange?.(newState);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  // ── BUSY state — no controls ──────────────────────────────────────────────
  if (floorState.status === 'BUSY') {
    return (
      <div className={compact ? '' : 'p-4'}>
        <SpecialistStatusPill state={floorState} />
      </div>
    );
  }

  // ── ON_BREAK state ────────────────────────────────────────────────────────
  if (floorState.status === 'ON_BREAK') {
    return (
      <div className={`flex flex-col gap-3 ${compact ? '' : 'p-4'}`}>
        <div className="flex items-center justify-between">
          <SpecialistStatusPill state={floorState} />
          {countdown && (
            <span className="text-sm font-mono text-orange-400 font-semibold tabular-nums">
              {countdown} remaining
            </span>
          )}
        </div>

        {!compact && floorState.expected_end_at && (
          <p className="text-xs text-text-secondary">
            Return expected at{' '}
            <span className="text-text-primary font-medium">
              {new Date(floorState.expected_end_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
            </span>
          </p>
        )}

        {error && <p className="text-xs text-red-400">{error}</p>}

        <motion.button
          whileTap={{ scale: 0.97 }}
          onClick={endBreak}
          disabled={loading}
          className="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-orange-500/20 hover:bg-orange-500/30 text-orange-400 font-medium text-sm transition-colors disabled:opacity-50"
        >
          <Play className="w-4 h-4" />
          {loading ? 'Ending break…' : 'End Break'}
        </motion.button>
      </div>
    );
  }

  // ── AVAILABLE state ───────────────────────────────────────────────────────
  return (
    <div className={`flex flex-col gap-3 ${compact ? '' : 'p-4'}`}>
      {!compact && <SpecialistStatusPill state={floorState} />}

      {error && <p className="text-xs text-red-400">{error}</p>}

      <AnimatePresence mode="wait">
        {!showPicker ? (
          <motion.button
            key="trigger"
            initial={{ opacity: 0, y: 4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -4 }}
            whileTap={{ scale: 0.97 }}
            onClick={() => setShowPicker(true)}
            className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-border-light text-text-secondary hover:text-text-primary hover:bg-surface font-medium text-sm transition-colors ${compact ? 'w-full' : 'w-full'}`}
          >
            <Coffee className="w-4 h-4" />
            Take a break
          </motion.button>
        ) : (
          <motion.div
            key="picker"
            initial={{ opacity: 0, y: 8, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 4, scale: 0.98 }}
            transition={{ type: 'spring', damping: 20, stiffness: 300 }}
            className="bg-surface border border-border-light rounded-2xl p-4 flex flex-col gap-3"
          >
            {/* Header */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-xl bg-orange-400/10 flex items-center justify-center">
                  <Coffee className="w-4 h-4 text-orange-400" />
                </div>
                <span className="text-sm font-semibold text-text-primary">Take a break</span>
              </div>
              <button
                onClick={() => { setShowPicker(false); setError(null); }}
                className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-border-light text-text-muted"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Duration label */}
            <p className="text-xs text-text-muted">How long?</p>

            {/* Presets */}
            <div className="grid grid-cols-4 gap-2">
              {PRESETS.map((p) => (
                <button
                  key={p}
                  onClick={() => { setSelectedPreset(p); setCustomMinutes(''); }}
                  className={`py-2 rounded-xl text-sm font-medium transition-all ${
                    selectedPreset === p
                      ? 'bg-orange-400/20 text-orange-400 border border-orange-400/40'
                      : 'bg-surface hover:bg-border-light text-text-secondary border border-transparent'
                  }`}
                >
                  {p} min
                </button>
              ))}
            </div>

            {/* Custom */}
            <div className="flex items-center gap-2">
              <button
                onClick={() => setSelectedPreset('custom')}
                className={`flex items-center gap-1 px-3 py-2 rounded-xl text-sm font-medium transition-all border ${
                  selectedPreset === 'custom'
                    ? 'bg-orange-400/20 text-orange-400 border-orange-400/40'
                    : 'bg-surface text-text-secondary border-transparent hover:bg-border-light'
                }`}
              >
                <ChevronDown className="w-3.5 h-3.5" />
                Custom
              </button>
              <AnimatePresence>
                {selectedPreset === 'custom' && (
                  <motion.div
                    initial={{ width: 0, opacity: 0 }}
                    animate={{ width: 'auto', opacity: 1 }}
                    exit={{ width: 0, opacity: 0 }}
                    className="flex items-center gap-1.5 overflow-hidden"
                  >
                    <input
                      type="number"
                      min={1}
                      max={480}
                      placeholder="mins"
                      value={customMinutes}
                      onChange={(e) => setCustomMinutes(e.target.value)}
                      className="w-20 px-3 py-2 rounded-xl bg-background border border-border-light text-sm text-text-primary focus:outline-none focus:border-orange-400/50 tabular-nums"
                      autoFocus
                    />
                    <span className="text-xs text-text-muted whitespace-nowrap">min</span>
                  </motion.div>
                )}
              </AnimatePresence>
            </div>

            {error && (
              <p className="text-xs text-red-400 px-1">{error}</p>
            )}

            {/* CTA */}
            <motion.button
              whileTap={{ scale: 0.97 }}
              onClick={startBreak}
              disabled={loading}
              className="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-xl bg-orange-400/20 hover:bg-orange-400/30 text-orange-400 font-semibold text-sm transition-colors disabled:opacity-50 border border-orange-400/20"
            >
              <Clock className="w-4 h-4" />
              {loading ? 'Starting break…' : 'Start Break'}
            </motion.button>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// FloorSpecialistCard — used in the manager/reception floor view
// Shows state clearly + allows authorized floor operators to manage breaks
// ─────────────────────────────────────────────────────────────────────────────

interface FloorSpecialistCardProps {
  specialistId: string;
  specialistName: string;
  specialistPhoto?: string;
  providerId: string;
  locationId?: string;
  floorState: SpecialistFloorState;
  /** Queue item being served (for BUSY state) */
  currentCustomer?: string;
  /** Can this viewer manage breaks for this specialist? */
  canManageBreak?: boolean;
  onStateChange?: (state: SpecialistFloorState) => void;
}

export function FloorSpecialistCard({
  specialistId,
  specialistName,
  specialistPhoto,
  providerId,
  locationId,
  floorState,
  currentCustomer,
  canManageBreak = false,
  onStateChange,
}: FloorSpecialistCardProps) {
  const [showBreakControls, setShowBreakControls] = useState(false);
  const countdown = useCountdown(
    floorState.status === 'ON_BREAK' ? floorState.expected_end_at : null
  );

  const borderColor =
    floorState.status === 'BUSY' ? 'border-amber-400/30' :
    floorState.status === 'ON_BREAK' ? 'border-orange-400/30' :
    'border-border-light';

  const glowColor =
    floorState.status === 'BUSY' ? 'shadow-amber-400/5' :
    floorState.status === 'ON_BREAK' ? 'shadow-orange-400/10' :
    '';

  return (
    <motion.div
      layout
      className={`bg-surface rounded-2xl border ${borderColor} p-4 flex flex-col gap-3 shadow-sm ${glowColor}`}
    >
      {/* Identity row */}
      <div className="flex items-center gap-3">
        <div className="relative shrink-0">
          {specialistPhoto ? (
            <img src={specialistPhoto} alt={specialistName} className="w-10 h-10 rounded-full object-cover" />
          ) : (
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold/60 to-amber-600/60 flex items-center justify-center text-white font-bold text-sm">
              {specialistName.charAt(0)}
            </div>
          )}
          {/* Live status dot */}
          <span
            className={`absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-surface ${
              floorState.status === 'BUSY' ? 'bg-amber-400' :
              floorState.status === 'ON_BREAK' ? 'bg-orange-400 animate-pulse' :
              'bg-emerald-400'
            }`}
          />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-text-primary truncate">{specialistName}</p>
          {floorState.status === 'BUSY' && currentCustomer && (
            <p className="text-xs text-text-muted truncate">Serving {currentCustomer}</p>
          )}
          {floorState.status === 'ON_BREAK' && (
            <p className="text-xs text-orange-400 font-mono tabular-nums">
              Returns in {countdown || '—'}
            </p>
          )}
          {floorState.status === 'AVAILABLE' && (
            <p className="text-xs text-emerald-400">Ready</p>
          )}
        </div>
        <SpecialistStatusPill state={floorState} />
      </div>

      {/* Return time for breaks */}
      {floorState.status === 'ON_BREAK' && floorState.expected_end_at && (
        <div className="flex items-center gap-2 px-3 py-2 bg-orange-400/5 rounded-xl">
          <Clock className="w-3.5 h-3.5 text-orange-400 shrink-0" />
          <span className="text-xs text-text-secondary">
            Expected return at{' '}
            <span className="text-orange-400 font-medium">
              {new Date(floorState.expected_end_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
            </span>
          </span>
        </div>
      )}

      {/* Floor operator controls */}
      {canManageBreak && floorState.status !== 'BUSY' && (
        <div>
          {!showBreakControls ? (
            <button
              onClick={() => setShowBreakControls(true)}
              className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-border-light text-text-muted hover:text-text-secondary hover:bg-border-light/50 text-xs font-medium transition-colors"
            >
              <Coffee className="w-3.5 h-3.5" />
              Manage Break
            </button>
          ) : (
            <BreakWidget
              specialistId={specialistId}
              providerId={providerId}
              locationId={locationId}
              isSpecialistPortal={false}
              initialState={floorState}
              onStateChange={(s) => {
                setShowBreakControls(false);
                onStateChange?.(s);
              }}
              compact
            />
          )}
        </div>
      )}
    </motion.div>
  );
}
