'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import {
  Loader2, Users, UserX, MoreVertical, ChevronDown,
  Shield, Star, Clock, GitBranch, Settings2, X, Save,
} from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

const ROLE_LABELS: Record<string, string> = {
  owner: 'Owner',
  manager: 'Manager',
  staff: 'Staff',
  receptionist: 'Receptionist',
};

const ROLE_COLORS: Record<string, string> = {
  owner: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
  manager: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
  staff: 'bg-green-500/20 text-green-300 border-green-500/30',
  receptionist: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
};

const DAYS_OF_WEEK = [
  'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
];

type ScheduleMode = 'INHERIT' | 'CUSTOM';

interface DaySchedule {
  day_of_week: string;
  open_time: string;
  close_time: string;
  is_closed: boolean;
}

function SpecialistScheduleDrawer({
  salonId,
  specialist,
  onClose,
}: {
  salonId: string;
  specialist: any;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const [scheduleMode, setScheduleMode] = useState<ScheduleMode>('INHERIT');
  const [schedules, setSchedules] = useState<DaySchedule[]>(
    DAYS_OF_WEEK.map(d => ({ day_of_week: d, open_time: '09:00', close_time: '18:00', is_closed: false })),
  );

  const { isLoading } = useQuery({
    queryKey: ['assignment-schedule', salonId, specialist.id],
    queryFn: () => apiClient.getAssignmentSchedule(salonId, specialist.id),
    onSuccess: (data: any) => {
      setScheduleMode(data?.schedule_mode ?? 'INHERIT');
      if (data?.data?.length) {
        const loaded: DaySchedule[] = DAYS_OF_WEEK.map(day => {
          const existing = data.data.find((s: any) => s.day_of_week === day);
          if (existing) {
            return {
              day_of_week: day,
              open_time: existing.open_time?.substring(0, 5) ?? '09:00',
              close_time: existing.close_time?.substring(0, 5) ?? '18:00',
              is_closed: !!existing.is_closed,
            };
          }
          return { day_of_week: day, open_time: '09:00', close_time: '18:00', is_closed: false };
        });
        setSchedules(loaded);
      }
    },
  } as any);

  const saveMutation = useMutation({
    mutationFn: () =>
      apiClient.updateAssignmentSchedule(
        salonId,
        specialist.id,
        scheduleMode,
        scheduleMode === 'CUSTOM' ? schedules : undefined,
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assignment-schedule', salonId, specialist.id] });
    },
  });

  const toggle = (day: string) =>
    setSchedules(p => p.map(s => s.day_of_week === day ? { ...s, is_closed: !s.is_closed } : s));

  const changeTime = (day: string, field: 'open_time' | 'close_time', v: string) =>
    setSchedules(p => p.map(s => s.day_of_week === day ? { ...s, [field]: v } : s));

  const name = specialist.display_name ?? specialist.name ?? 'Specialist';

  return (
    <>
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 z-40" onClick={onClose} />

      {/* Drawer */}
      <div className="fixed right-0 top-0 h-full w-full max-w-md bg-[#0d0d0d] border-l border-white/10 z-50 flex flex-col shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-white/10">
          <div>
            <h3 className="text-text-primary font-semibold flex items-center gap-2">
              <Clock className="w-4 h-4 text-[#FFD700]" />
              Working Hours
            </h3>
            <p className="text-text-secondary text-xs mt-0.5">{name}</p>
          </div>
          <button onClick={onClose} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/5 text-text-secondary">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="w-6 h-6 animate-spin text-[#FFD700]" />
            </div>
          ) : (
            <>
              {/* Mode selector */}
              <div className="space-y-3">
                <p className="text-sm font-medium text-text-primary">Schedule Source</p>
                <div className="grid grid-cols-2 gap-3">
                  {(['INHERIT', 'CUSTOM'] as ScheduleMode[]).map(mode => (
                    <button
                      key={mode}
                      onClick={() => setScheduleMode(mode)}
                      className={`flex items-start gap-2 p-3 rounded-xl border text-left transition-all ${
                        scheduleMode === mode
                          ? 'border-[#FFD700] bg-[#FFD700]/10'
                          : 'border-white/10 bg-white/[0.02] hover:border-white/20'
                      }`}
                    >
                      {mode === 'INHERIT'
                        ? <GitBranch className={`w-4 h-4 mt-0.5 flex-shrink-0 ${scheduleMode === mode ? 'text-[#FFD700]' : 'text-text-secondary'}`} />
                        : <Settings2 className={`w-4 h-4 mt-0.5 flex-shrink-0 ${scheduleMode === mode ? 'text-[#FFD700]' : 'text-text-secondary'}`} />
                      }
                      <div>
                        <p className={`text-xs font-semibold ${scheduleMode === mode ? 'text-[#FFD700]' : 'text-text-primary'}`}>
                          {mode === 'INHERIT' ? 'Salon Hours' : 'Custom Hours'}
                        </p>
                        <p className="text-[10px] text-text-secondary mt-0.5 leading-tight">
                          {mode === 'INHERIT'
                            ? 'Follow the salon\'s hours.'
                            : 'Set personal hours.'}
                        </p>
                      </div>
                    </button>
                  ))}
                </div>

                {scheduleMode === 'INHERIT' && (
                  <div className="px-4 py-3 rounded-lg bg-blue-500/10 border border-blue-500/20 text-xs text-blue-300">
                    {name} will follow the salon operating hours. Switch to Custom to set personal hours.
                  </div>
                )}
              </div>

              {/* Day schedule editor */}
              {scheduleMode === 'CUSTOM' && (
                <div className="bg-white/[0.02] border border-white/5 rounded-xl overflow-hidden">
                  {schedules.map((s, i) => (
                    <div key={s.day_of_week} className={`p-3 ${i !== schedules.length - 1 ? 'border-b border-white/5' : ''}`}>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-text-primary text-sm font-medium">{s.day_of_week}</span>
                        <button
                          onClick={() => toggle(s.day_of_week)}
                          className={`w-10 h-5 rounded-full transition-colors flex items-center p-0.5 ${
                            !s.is_closed ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
                          }`}
                        >
                          <div className={`w-4 h-4 rounded-full bg-[#0A0A0A] transition-transform ${!s.is_closed ? 'translate-x-5' : 'translate-x-0.5'}`} />
                        </button>
                      </div>
                      {s.is_closed ? (
                        <div className="text-center text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg py-1.5">Closed</div>
                      ) : (
                        <div className="flex items-center gap-2">
                          <input
                            type="time"
                            value={s.open_time}
                            onChange={e => changeTime(s.day_of_week, 'open_time', e.target.value)}
                            className="flex-1 bg-black border border-white/10 rounded-lg px-2 py-1.5 text-text-primary text-xs focus:outline-none focus:border-[#FFD700]"
                          />
                          <span className="text-text-secondary text-xs">–</span>
                          <input
                            type="time"
                            value={s.close_time}
                            onChange={e => changeTime(s.day_of_week, 'close_time', e.target.value)}
                            className="flex-1 bg-black border border-white/10 rounded-lg px-2 py-1.5 text-text-primary text-xs focus:outline-none focus:border-[#FFD700]"
                          />
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-white/10 space-y-2">
          {saveMutation.isSuccess && <p className="text-green-400 text-xs text-center">Schedule saved!</p>}
          {saveMutation.isError && <p className="text-red-400 text-xs text-center">Failed to save. Try again.</p>}
          <button
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending}
            className="w-full flex items-center justify-center gap-2 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black text-sm font-semibold rounded-xl hover:opacity-90 disabled:opacity-50 transition-opacity"
          >
            {saveMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            Save Schedule
          </button>
        </div>
      </div>
    </>
  );
}

export default function StaffRosterTab() {
  const { salonId } = useRole();
  const queryClient = useQueryClient();
  const [openMenuId, setOpenMenuId] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [newRole, setNewRole] = useState<string>('');
  const [scheduleDrawerSpecialist, setScheduleDrawerSpecialist] = useState<any | null>(null);
  const { salonSlug } = useRole();

  const { data, isLoading } = useQuery({
    queryKey: ['salon-specialists', salonId, salonSlug],
    queryFn: () => apiClient.getSalonSpecialistsWithFallback(salonId || undefined, salonSlug || undefined),
    enabled: !!salonId || !!salonSlug,
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) =>
      apiClient.updateSalonSpecialist(salonId!, id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salon-specialists', salonId] });
      setEditingId(null);
    },
  });

  const removeMutation = useMutation({
    mutationFn: (id: string) => apiClient.removeSalonSpecialist(salonId!, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salon-specialists', salonId] });
    },
  });

  const specialists: any[] = Array.isArray(data) ? data : (data?.data ?? []);

  if (isLoading) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-semibold text-text-primary mb-1 flex items-center gap-2">
            <Users className="w-5 h-5 text-[#FFD700]" />
            Staff Roster
          </h2>
          <p className="text-sm text-text-secondary">
            Manage your team members and their roles at this location.
          </p>
        </div>
        <span className="text-sm text-text-secondary">
          {specialists.length} member{specialists.length !== 1 ? 's' : ''}
        </span>
      </div>

      {specialists.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <Users className="w-12 h-12 text-text-secondary/30 mb-4" />
          <p className="text-text-secondary text-sm">No team members assigned to this location yet.</p>
          <p className="text-text-secondary/50 text-xs mt-1">Invite specialists from the Specialists page.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {specialists.map((specialist: any) => {
            const role = specialist.pivot?.role ?? 'staff';
            const isActive = specialist.pivot?.active !== false;
            const isEditing = editingId === specialist.id;

            return (
              <div
                key={specialist.id}
                className={`bg-white/[0.02] border rounded-xl p-4 transition-all ${
                  isActive ? 'border-white/5' : 'border-white/5 opacity-50'
                }`}
              >
                <div className="flex items-center gap-4">
                  {/* Avatar */}
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#FFD700]/30 to-[#C9A227]/10 flex items-center justify-center flex-shrink-0 border border-[#FFD700]/20">
                    <span className="text-[#FFD700] font-semibold text-sm">
                      {(specialist.display_name ?? specialist.name ?? '?')[0].toUpperCase()}
                    </span>
                  </div>

                  {/* Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <p className="text-text-primary font-medium text-sm truncate">
                        {specialist.display_name ?? specialist.name ?? 'Unknown'}
                      </p>
                      {specialist.rating && (
                        <span className="flex items-center gap-1 text-xs text-[#FFD700]">
                          <Star className="w-3 h-3 fill-current" />
                          {Number(specialist.rating).toFixed(1)}
                        </span>
                      )}
                    </div>
                    <p className="text-text-secondary text-xs truncate">
                      {specialist.handle ? `@${specialist.handle}` : specialist.email ?? ''}
                    </p>
                  </div>

                  {/* Role badge */}
                  {isEditing ? (
                    <div className="flex items-center gap-2">
                      <select
                        value={newRole}
                        onChange={(e) => setNewRole(e.target.value)}
                        className="bg-black border border-[#FFD700]/40 rounded-lg px-2 py-1 text-text-primary text-sm focus:outline-none focus:border-[#FFD700]"
                      >
                        {Object.entries(ROLE_LABELS).map(([val, label]) => (
                          <option key={val} value={val}>{label}</option>
                        ))}
                      </select>
                      <button
                        onClick={() => updateMutation.mutate({ id: specialist.id, data: { role: newRole } })}
                        disabled={updateMutation.isPending}
                        className="px-3 py-1 bg-[#FFD700] text-black text-xs font-semibold rounded-lg hover:opacity-90 disabled:opacity-50"
                      >
                        {updateMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : 'Save'}
                      </button>
                      <button
                        onClick={() => setEditingId(null)}
                        className="text-text-secondary text-xs hover:text-text-primary"
                      >
                        Cancel
                      </button>
                    </div>
                  ) : (
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium border ${ROLE_COLORS[role] ?? ROLE_COLORS.staff}`}>
                      {ROLE_LABELS[role] ?? role}
                    </span>
                  )}

                  {/* Actions dropdown */}
                  {!isEditing && (
                    <div className="relative">
                      <button
                        onClick={() => setOpenMenuId(openMenuId === specialist.id ? null : specialist.id)}
                        className="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-white/5 text-text-secondary transition-colors"
                      >
                        <MoreVertical className="w-4 h-4" />
                      </button>
                      {openMenuId === specialist.id && (
                        <div className="absolute right-0 top-full mt-1 w-48 bg-[#111] border border-white/10 rounded-xl shadow-xl z-10 overflow-hidden">
                          <button
                            onClick={() => {
                              setEditingId(specialist.id);
                              setNewRole(role);
                              setOpenMenuId(null);
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-text-primary hover:bg-white/5 flex items-center gap-2"
                          >
                            <Shield className="w-3.5 h-3.5 text-text-secondary" />
                            Change Role
                          </button>
                          <button
                            onClick={() => {
                              setScheduleDrawerSpecialist(specialist);
                              setOpenMenuId(null);
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-text-primary hover:bg-white/5 flex items-center gap-2"
                          >
                            <Clock className="w-3.5 h-3.5 text-text-secondary" />
                            Manage Hours
                          </button>
                          <button
                            onClick={() => {
                              updateMutation.mutate({ id: specialist.id, data: { active: !isActive } });
                              setOpenMenuId(null);
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-text-primary hover:bg-white/5 flex items-center gap-2"
                          >
                            <ChevronDown className="w-3.5 h-3.5 text-text-secondary" />
                            {isActive ? 'Deactivate' : 'Activate'}
                          </button>
                          <button
                            onClick={() => {
                              if (confirm(`Remove ${specialist.display_name ?? specialist.name} from this salon?`)) {
                                removeMutation.mutate(specialist.id);
                              }
                              setOpenMenuId(null);
                            }}
                            className="w-full text-left px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 flex items-center gap-2"
                          >
                            <UserX className="w-3.5 h-3.5" />
                            Remove
                          </button>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Schedule Drawer */}
      {scheduleDrawerSpecialist && salonId && (
        <SpecialistScheduleDrawer
          salonId={salonId}
          specialist={scheduleDrawerSpecialist}
          onClose={() => setScheduleDrawerSpecialist(null)}
        />
      )}

      {/* Click outside to close menu */}
      {openMenuId && (
        <div className="fixed inset-0 z-0" onClick={() => setOpenMenuId(null)} />
      )}
    </div>
  );
}
