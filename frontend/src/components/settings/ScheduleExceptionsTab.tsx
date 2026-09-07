'use client';

import { useMemo, useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import {
  CalendarClock,
  Plus,
  Loader2,
  Pencil,
  Trash2,
  CalendarX2,
  PlaneTakeoff,
  GraduationCap,
  ZapOff,
  Calendar,
  CircleAlert,
  Sparkles,
  X,
} from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

type ExceptionType =
  | 'HOLIDAY'
  | 'VACATION'
  | 'TRAINING'
  | 'CLOSURE'
  | 'POWER_OUTAGE'
  | 'EVENT'
  | 'OTHER';

type ExceptionScope = 'provider' | 'salon' | 'assignment';

interface ScheduleException {
  id: string;
  scope_level: ExceptionScope;
  assignment_id?: string | null;
  start_date: string;
  end_date?: string | null;
  title: string;
  notes?: string | null;
  type: ExceptionType;
  is_closed: boolean;
  open_time?: string | null;
  close_time?: string | null;
  break_start?: string | null;
  break_end?: string | null;
  recurring_yearly: boolean;
}

const TYPE_OPTIONS: {
  value: ExceptionType;
  label: string;
  hint: string;
  Icon: typeof CalendarClock;
}[] = [
  { value: 'HOLIDAY',      label: 'Public Holiday', hint: 'Entire business closed.', Icon: CalendarX2 },
  { value: 'VACATION',     label: 'Vacation',       hint: 'Owner / staff away.',    Icon: PlaneTakeoff },
  { value: 'TRAINING',     label: 'Training',       hint: 'Typically half-day.',    Icon: GraduationCap },
  { value: 'CLOSURE',      label: 'Private Closure',hint: 'Private event / deep clean.', Icon: Calendar },
  { value: 'POWER_OUTAGE', label: 'Power Outage',   hint: 'Generator unavailable.', Icon: ZapOff },
  { value: 'EVENT',        label: 'Private Event',  hint: 'Still open but restricted.', Icon: Sparkles },
  { value: 'OTHER',        label: 'Other',          hint: 'Explain in notes.',      Icon: CircleAlert },
];

const emptyForm = (): Omit<ScheduleException, 'id'> => ({
  scope_level: 'provider',
  assignment_id: null,
  start_date: '',
  end_date: null,
  title: '',
  notes: null,
  type: 'HOLIDAY',
  is_closed: true,
  open_time: null,
  close_time: null,
  break_start: null,
  break_end: null,
  recurring_yearly: false,
});

function formatDate(d: string | null | undefined): string {
  if (!d) return '';
  const date = new Date(d);
  if (Number.isNaN(date.getTime())) return d;
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function typeMeta(type: ExceptionType) {
  return TYPE_OPTIONS.find(t => t.value === type) ?? TYPE_OPTIONS[TYPE_OPTIONS.length - 1];
}

export default function ScheduleExceptionsTab() {
  const { salonId, specialists, isLoading: roleLoading } = useRole();
  const queryClient = useQueryClient();
  const [scope, setScope] = useState<ExceptionScope>('provider');
  const [assignmentId, setAssignmentId] = useState<string | undefined>(undefined);
  const [hasMultipleLocations, setHasMultipleLocations] = useState(false);

  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<ScheduleException | null>(null);
  const [form, setForm] = useState<Omit<ScheduleException, 'id'>>(emptyForm());

  const validSalonId = salonId && salonId !== 'null' && salonId !== 'undefined' ? salonId : null;
  const canMutate = !!validSalonId && !roleLoading;

  // Get salon data to determine location count
  const { data: salonData } = useQuery({
    queryKey: ['salon-with-provider', validSalonId],
    queryFn: () => apiClient.getSalonWithProvider(validSalonId!),
    enabled: !!validSalonId && !roleLoading,
  });

  useEffect(() => {
    if (salonData?.provider_id) {
      // For now, assume single location. In future, check provider's salon count
      setHasMultipleLocations(false);
    }
  }, [salonData]);

  const queryKey = ['schedule-exceptions', validSalonId, scope, assignmentId];

  const { data, isLoading } = useQuery({
    queryKey,
    queryFn: () => apiClient.getScheduleExceptions(validSalonId!, scope, assignmentId),
    enabled: !!validSalonId && !roleLoading,
  });

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => apiClient.createScheduleException(validSalonId!, payload),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey }); resetEditor(); },
  });

  const updateMutation = useMutation({
    mutationFn: (payload: { id: string; data: Record<string, unknown> }) =>
      apiClient.updateScheduleException(validSalonId!, payload.id, payload.data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey }); resetEditor(); },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => apiClient.deleteScheduleException(validSalonId!, id),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey }); },
  });

  const rows: ScheduleException[] = data?.data ?? [];

  function resetEditor() {
    setEditorOpen(false);
    setEditing(null);
    setForm(emptyForm());
  }

  function openCreate() {
    setEditing(null);
    const defaultForm = { ...emptyForm(), scope_level: scope } as Omit<ScheduleException, 'id'>;
    if (scope === 'assignment' && assignmentId) {
      (defaultForm as any).assignment_id = assignmentId;
    }
    setForm(defaultForm);
    setEditorOpen(true);
  }

  function openEdit(e: ScheduleException) {
    setEditing(e);
    setForm({
      scope_level: e.scope_level,
      start_date: e.start_date,
      end_date: e.end_date ?? null,
      title: e.title,
      notes: e.notes ?? null,
      type: e.type,
      is_closed: e.is_closed,
      open_time: e.open_time ? e.open_time.substring(0, 5) : null,
      close_time: e.close_time ? e.close_time.substring(0, 5) : null,
      break_start: e.break_start ? e.break_start.substring(0, 5) : null,
      break_end: e.break_end ? e.break_end.substring(0, 5) : null,
      recurring_yearly: e.recurring_yearly,
    });
    setEditorOpen(true);
  }

  function submit() {
    if (!canMutate) return;
    const payload: Record<string, unknown> = {
      scope_level: form.scope_level,
      start_date: form.start_date,
      end_date: form.end_date ?? null,
      title: form.title,
      notes: form.notes ?? null,
      type: form.type,
      is_closed: form.is_closed,
      open_time: form.open_time ?? null,
      close_time: form.close_time ?? null,
      break_start: form.break_start ?? null,
      break_end: form.break_end ?? null,
      recurring_yearly: form.recurring_yearly,
    };
    if (form.scope_level === 'assignment') {
      payload.assignment_id = assignmentId;
    }
    if (editing) {
      updateMutation.mutate({ id: editing.id, data: payload });
    } else {
      createMutation.mutate(payload);
    }
  }

  const scopesDisabled = useMemo(
    () => ({
      assignment: !(assignmentId ?? specialists?.length),
    }),
    [assignmentId, specialists],
  );

  if (roleLoading || isLoading) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (!validSalonId) {
    return (
      <div className="flex flex-col justify-center items-center py-20 text-center space-y-3">
        <CalendarClock className="w-10 h-10 text-amber-400" />
        <h3 className="text-lg font-semibold text-text-primary">No salon selected</h3>
        <p className="text-sm text-text-secondary max-w-md">
          Salon context is not available. Please sign out and back in, or complete onboarding.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-semibold text-text-primary mb-1 flex items-center gap-2">
            <CalendarClock className="w-5 h-5 text-[#FFD700]" />
            Holidays &amp; Closures
          </h2>
          <p className="text-sm text-text-secondary">
            Manage exceptions to the weekly schedule — public holidays, vacations,
            training days and temporary closures.
          </p>
        </div>
        <button
          onClick={openCreate}
          disabled={!canMutate}
          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity"
        >
          <Plus className="w-4 h-4" />
          Add Exception
        </button>
      </div>

      {/* Scope tabs - contextually flexible based on business structure */}
      <div className="flex flex-wrap gap-3 p-1 bg-white/[0.02] border border-white/5 rounded-xl w-fit">
        {(() => {
          const options = hasMultipleLocations
            ? [
                ['provider', 'Everyone'],
                ['salon', 'This location'],
                ['assignment', 'One specialist'],
              ] as const
            : [
                ['provider', 'Everyone'],
                ['assignment', 'One specialist'],
              ] as const;

          return options.map(([key, label]) => (
            <button
              key={key}
              disabled={key === 'assignment' && scopesDisabled.assignment}
              onClick={() => { setScope(key); }}
              className={`relative px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                scope === key
                  ? 'bg-[#FFD700]/10 text-[#FFD700] ring-1 ring-[#FFD700]/30'
                  : 'text-text-secondary hover:text-text-primary'
              } ${key === 'assignment' && scopesDisabled.assignment ? 'opacity-40 cursor-not-allowed' : ''}`}
            >
              {label}
            </button>
          ));
        })()}
      </div>

      {scope === 'assignment' && (
        <div className="sm:w-80 bg-white/[0.02] border border-white/5 rounded-xl px-4 py-3">
          <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">
            Specialist
          </label>
          <select
            value={assignmentId ?? ''}
            onChange={(e) => setAssignmentId(e.target.value || undefined)}
            className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-[#FFD700]"
          >
            <option value="">Pick a specialist…</option>
            {(specialists ?? []).map((s: any) => (
              <option key={s.id} value={s.id}>{s.name ?? s.full_name ?? 'Specialist'}</option>
            ))}
          </select>
        </div>
      )}

      {isLoading && (
        <div className="flex justify-center items-center py-20">
          <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
        </div>
      )}

      {!isLoading && rows.length === 0 && (
        <div className="rounded-2xl border border-dashed border-white/10 px-6 py-16 text-center">
          <CalendarX2 className="w-10 h-10 text-white/20 mx-auto mb-3" />
          <p className="text-text-primary font-medium">No {scope} exceptions yet.</p>
          <p className="text-text-secondary text-sm mt-1">
            Add a public holiday or closure to override the weekly schedule.
          </p>
        </div>
      )}

      {!isLoading && rows.length > 0 && (
        <ul className="space-y-3">
          {rows.map((e) => {
            const meta = typeMeta(e.type);
            const Icon = meta.Icon;
            const dateRange =
              !e.end_date || e.end_date === e.start_date
                ? formatDate(e.start_date)
                : `${formatDate(e.start_date)} → ${formatDate(e.end_date)}`;
            return (
              <li
                key={e.id}
                className="group flex flex-col sm:flex-row sm:items-center gap-4 p-4 rounded-xl border border-white/5 bg-white/[0.02] hover:border-white/10 transition-colors"
              >
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                  e.is_closed ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-300'
                }`}>
                  <Icon className="w-5 h-5" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <p className="font-medium text-text-primary truncate">{e.title}</p>
                    <span className="text-[10px] uppercase font-mono tracking-wider px-2 py-0.5 rounded-md bg-white/5 text-text-secondary">
                      {meta.label}
                    </span>
                    <span className="text-[10px] uppercase font-mono tracking-wider px-2 py-0.5 rounded-md bg-[#FFD700]/10 text-[#FFD700]/80">
                      {e.scope_level}
                    </span>
                    {e.recurring_yearly && (
                      <span className="text-[10px] uppercase font-mono tracking-wider px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-300">
                        Yearly
                      </span>
                    )}
                  </div>
                  <p className="text-sm text-text-secondary mt-1">{dateRange}{!e.is_closed && e.open_time && e.close_time && ` · ${e.open_time.substring(0, 5)}–${e.close_time.substring(0, 5)}`}</p>
                  {e.notes && <p className="text-xs text-text-secondary/70 mt-1 italic">"{e.notes}"</p>}
                </div>
                <div className="flex items-center gap-2 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                  <button
                    onClick={() => openEdit(e)}
                    className="p-2 rounded-lg text-text-secondary hover:text-text-primary hover:bg-white/5"
                    aria-label="Edit"
                  >
                    <Pencil className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => { if (confirm('Delete this exception?')) deleteMutation.mutate(e.id); }}
                    disabled={deleteMutation.isPending}
                    className="p-2 rounded-lg text-red-400/80 hover:text-red-400 hover:bg-red-500/5 disabled:opacity-50"
                    aria-label="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
      )}

      {editorOpen && (
        <div className="fixed inset-0 z-[100] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border border-white/10 bg-[#0c0c0c]">
            <div className="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b border-white/5 bg-[#0c0c0c]">
              <h3 className="font-semibold text-text-primary">{editing ? 'Edit exception' : 'Add exception'}</h3>
              <button
                onClick={resetEditor}
                className="p-2 rounded-lg text-text-secondary hover:text-text-primary hover:bg-white/5"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-6 space-y-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Title *</label>
                  <input
                    type="text"
                    value={form.title}
                    onChange={(e) => setForm({ ...form, title: e.target.value })}
                    placeholder="e.g. Christmas Day"
                    className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Type *</label>
                  <select
                    value={form.type}
                    onChange={(e) => setForm({ ...form, type: e.target.value as ExceptionType })}
                    className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-[#FFD700]"
                  >
                    {TYPE_OPTIONS.map(opt => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Start date *</label>
                  <input
                    type="date"
                    value={form.start_date}
                    onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                    className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">End date (optional)</label>
                  <input
                    type="date"
                    value={form.end_date ?? ''}
                    onChange={(e) => setForm({ ...form, end_date: e.target.value || null })}
                    className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                  />
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-6 bg-white/[0.02] border border-white/5 rounded-xl px-4 py-3">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.is_closed}
                    onChange={(e) => setForm({ ...form, is_closed: e.target.checked })}
                    className="accent-[#FFD700]"
                  />
                  <span className="text-sm text-text-primary">Closed all day</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.recurring_yearly}
                    onChange={(e) => setForm({ ...form, recurring_yearly: e.target.checked })}
                    className="accent-[#FFD700]"
                  />
                  <span className="text-sm text-text-primary">Repeat every year</span>
                </label>
              </div>

              {!form.is_closed && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Open at *</label>
                    <input
                      type="time"
                      value={form.open_time ?? ''}
                      onChange={(e) => setForm({ ...form, open_time: e.target.value || null })}
                      className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Close at *</label>
                    <input
                      type="time"
                      value={form.close_time ?? ''}
                      onChange={(e) => setForm({ ...form, close_time: e.target.value || null })}
                      className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Break start</label>
                    <input
                      type="time"
                      value={form.break_start ?? ''}
                      onChange={(e) => setForm({ ...form, break_start: e.target.value || null })}
                      className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Break end</label>
                    <input
                      type="time"
                      value={form.break_end ?? ''}
                      onChange={(e) => setForm({ ...form, break_end: e.target.value || null })}
                      className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                    />
                  </div>
                </div>
              )}

              <div>
                <label className="block text-xs font-mono uppercase tracking-wider text-text-secondary mb-2">Notes</label>
                <textarea
                  rows={2}
                  value={form.notes ?? ''}
                  onChange={(e) => setForm({ ...form, notes: e.target.value || null })}
                  placeholder="Optional – explain the reason."
                  className="w-full bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700] resize-none"
                />
              </div>
            </div>

            <div className="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-white/5 bg-[#0c0c0c]">
              <button
                onClick={resetEditor}
                className="px-4 py-2 text-sm font-medium rounded-lg text-text-secondary hover:text-text-primary hover:bg-white/5"
              >
                Cancel
              </button>
              <button
                onClick={submit}
                disabled={!form.title || !form.start_date || (!form.is_closed && (!form.open_time || !form.close_time)) || createMutation.isPending || updateMutation.isPending}
                className="px-5 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity flex items-center gap-2"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-4 h-4 animate-spin" />}
                {editing ? 'Save changes' : 'Add exception'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
