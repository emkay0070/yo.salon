'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { Loader2, Save, Clock, Rocket, ShieldAlert } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

const DAYS_OF_WEEK = [
  'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
];

interface Schedule {
  day_of_week: string;
  open_time: string | null;
  close_time: string | null;
  is_closed: boolean;
}

type ScheduleMode = 'INHERIT' | 'CUSTOM';
type PublishStatus = 'EMPTY' | 'ACTIVE' | 'DRAFT';

function hoursComplete(schedules: Schedule[]) {
  return schedules.every(s => (s.is_closed || (s.open_time && s.close_time)));
}

export default function OperatingHoursTab() {
  const { salonId, isLoading: roleLoading } = useRole();
  const queryClient = useQueryClient();
  const [schedules, setSchedules] = useState<Schedule[]>([]);
  const [publishStatus, setPublishStatus] = useState<PublishStatus>('EMPTY');
  const [hasMultipleBranches, setHasMultipleBranches] = useState(false);
  const [providerId, setProviderId] = useState<string | null>(null);

  const validSalonId = salonId && salonId !== 'null' && salonId !== 'undefined' ? salonId : null;

  const { data: salonData } = useQuery({
    queryKey: ['salon-with-provider', validSalonId],
    queryFn: () => apiClient.getSalonWithProvider(validSalonId!),
    enabled: !!validSalonId && !roleLoading,
  });

  useEffect(() => {
    if (salonData?.provider_id) {
      setProviderId(salonData.provider_id);
      // For now, assume single location. In future, check provider's salon count
      setHasMultipleBranches(false);
    }
  }, [salonData]);

  // Fetch schedules based on whether it's single or multi-location
  const { data, isLoading } = useQuery({
    queryKey: hasMultipleBranches ? ['salon-schedules', validSalonId] : ['provider-schedules', providerId],
    queryFn: () => {
      if (!providerId) {
        throw new Error('Provider ID is required to fetch schedules');
      }
      return hasMultipleBranches
        ? apiClient.getSalonSchedules(validSalonId!)
        : apiClient.getProviderSchedules(providerId);
    },
    enabled: !!validSalonId && !roleLoading && !!providerId,
  });

  useEffect(() => {
    if (data) {
      setPublishStatus((data.publish_status as PublishStatus) ?? 'EMPTY');

      const backendSchedules: any[] = data.data ?? [];
      const initialSchedules = DAYS_OF_WEEK.map(day => {
        const existing = backendSchedules.find((s: any) => s.day_of_week === day);
        if (existing) {
          const openTime = existing.open_time ? existing.open_time.substring(0, 5) : '';
          const closeTime = existing.close_time ? existing.close_time.substring(0, 5) : '';
          return { day_of_week: day, open_time: openTime, close_time: closeTime, is_closed: existing.is_closed };
        }
        return { day_of_week: day, open_time: '09:00', close_time: '18:00', is_closed: false };
      });
      setSchedules(initialSchedules);
    } else if (!isLoading) {
      setSchedules(DAYS_OF_WEEK.map(day => ({
        day_of_week: day, open_time: '09:00', close_time: '18:00', is_closed: false,
      })));
    }
  }, [data, isLoading]);

  const willSaveAsDraft = !hoursComplete(schedules);
  const canSave = !!validSalonId && !roleLoading;

  const updateMutation = useMutation({
    mutationFn: () => {
      if (!providerId) {
        throw new Error('Provider ID is required to update schedules');
      }
      return hasMultipleBranches
        ? apiClient.updateSalonSchedules(validSalonId!, 'CUSTOM', schedules)
        : apiClient.updateProviderSchedules(providerId, schedules);
    },
    onSuccess: (resp: any) => {
      queryClient.invalidateQueries({ queryKey: hasMultipleBranches ? ['salon-schedules', validSalonId] : ['provider-schedules', providerId] });
      if (resp?.publish_status) setPublishStatus(resp.publish_status);
    },
  });

  const publishMutation = useMutation({
    mutationFn: () => {
      if (!providerId) {
        throw new Error('Provider ID is required to publish schedules');
      }
      return hasMultipleBranches
        ? apiClient.publishSalonSchedules(validSalonId!)
        : apiClient.publishProviderSchedules(providerId);
    },
    onSuccess: (resp: any) => {
      queryClient.invalidateQueries({ queryKey: hasMultipleBranches ? ['salon-schedules', validSalonId] : ['provider-schedules', providerId] });
      if (resp?.publish_status) setPublishStatus(resp.publish_status);
    },
  });

  const handleToggleClosed = (day: string) => {
    setSchedules(prev => prev.map(s =>
      s.day_of_week === day ? { ...s, is_closed: !s.is_closed } : s,
    ));
  };

  const handleTimeChange = (day: string, field: 'open_time' | 'close_time', value: string) => {
    setSchedules(prev => prev.map(s =>
      s.day_of_week === day ? { ...s, [field]: value } : s,
    ));
  };

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
        <ShieldAlert className="w-10 h-10 text-amber-400" />
        <h3 className="text-lg font-semibold text-text-primary">No salon selected</h3>
        <p className="text-sm text-text-secondary max-w-md">
          Salon context is not available. Please sign out and back in, or complete onboarding.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h2 className="text-xl font-semibold text-text-primary mb-1 flex items-center gap-2">
            <Clock className="w-5 h-5 text-[#FFD700]" />
            Operating Hours
            <span className={`ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase tracking-wider ${
              publishStatus === 'ACTIVE'
                ? 'bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-500/30'
                : publishStatus === 'DRAFT'
                ? 'bg-amber-500/10 text-amber-300 ring-1 ring-amber-500/30'
                : 'bg-white/5 text-text-secondary ring-1 ring-white/10'
            }`}>
              {publishStatus === 'ACTIVE' && <span className="w-1.5 h-1.5 rounded-full bg-emerald-400" />}
              {publishStatus === 'DRAFT' && <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />}
              {publishStatus === 'ACTIVE' ? 'Published' : publishStatus === 'DRAFT' ? 'Draft — not live' : 'Not configured'}
            </span>
          </h2>
          <p className="text-sm text-text-secondary">Configure when your salon is open for bookings.</p>
        </div>

        <div className="flex items-center gap-2">
          {publishStatus === 'DRAFT' && (
            <button
              onClick={() => publishMutation.mutate()}
              disabled={!canSave || publishMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity"
            >
              {publishMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Rocket className="w-4 h-4" />}
              Publish
            </button>
          )}
          <button
            onClick={() => updateMutation.mutate()}
            disabled={!canSave || updateMutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity"
          >
            {updateMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            {willSaveAsDraft ? 'Save Draft' : 'Save Changes'}
          </button>
        </div>
      </div>

      {willSaveAsDraft && (
        <div className="rounded-xl px-4 py-3 bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
          This schedule is missing open or close times on some days — it will be saved as a draft and will not affect bookings until you click <strong>Publish</strong>.
        </div>
      )}

      {/* Schedule Editor */}
      <div>
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-1 overflow-hidden">
          {schedules.map((schedule, index) => (
            <div
              key={schedule.day_of_week}
              className={`flex flex-col sm:flex-row sm:items-center justify-between p-4 ${index !== schedules.length - 1 ? 'border-b border-white/5' : ''}`}
            >
                <div className="flex items-center justify-between w-full sm:w-1/3 mb-4 sm:mb-0">
                  <span className="text-text-primary font-medium w-28">{schedule.day_of_week}</span>

                  {/* Toggle switch */}
                  <button
                    onClick={() => handleToggleClosed(schedule.day_of_week)}
                    className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                      !schedule.is_closed ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
                    }`}
                  >
                    <div
                      className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                        !schedule.is_closed ? 'translate-x-6' : 'translate-x-0.5'
                      }`}
                    />
                  </button>
                </div>

                <div className="flex items-center gap-4 w-full sm:w-auto">
                  {schedule.is_closed ? (
                    <div className="px-4 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm font-medium w-full sm:w-[240px] text-center border border-red-500/20">
                      Closed
                    </div>
                  ) : (
                    <div className="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                      <input
                        type="time"
                        value={schedule.open_time || ''}
                        onChange={(e) => handleTimeChange(schedule.day_of_week, 'open_time', e.target.value)}
                        className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700] w-[110px]"
                      />
                      <span className="text-text-secondary text-sm">to</span>
                      <input
                        type="time"
                        value={schedule.close_time || ''}
                        onChange={(e) => handleTimeChange(schedule.day_of_week, 'close_time', e.target.value)}
                        className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary text-sm focus:outline-none focus:border-[#FFD700] w-[110px]"
                      />
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
      </div>

      {(updateMutation.isSuccess || publishMutation.isSuccess) && (
        <div className="text-green-400 text-sm text-right mt-2">
          {publishMutation.isSuccess ? 'Schedule published and applied to bookings.' : (willSaveAsDraft ? 'Draft saved — publish when ready.' : 'Schedules successfully updated!')}
        </div>
      )}
      {(updateMutation.isError || publishMutation.isError) && (
        <div className="text-red-400 text-sm text-right mt-2">
          {(updateMutation.error as any)?.response?.data?.message || (publishMutation.error as any)?.response?.data?.message || 'Failed to update schedules. Please try again.'}
        </div>
      )}
    </div>
  );
}
